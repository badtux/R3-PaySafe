const jwt = require("jsonwebtoken");
const axios = require("axios");

const {
  OAUTH_JWT_SECRET,
  JWT_SECRET,
  OAUTH_USERINFO_ENDPOINT
} = process.env;

// Optional: Configure axios instance for reuse
const oauthClient = axios.create({
  timeout: 8000,
  headers: {
    'Accept': 'application/json',
  }
});

async function validateOAuthToken(token) {
  if (!OAUTH_USERINFO_ENDPOINT) {
    console.warn("OAUTH_USERINFO_ENDPOINT not configured. Skipping OAuth validation.");
    return { valid: false };
  }

  try {
    const response = await oauthClient.get(OAUTH_USERINFO_ENDPOINT, {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });

    // Axios resolves only on 2xx
    return { valid: true, user: response.data };
  } catch (err) {
    const status = err.response?.status;
    const data = err.response?.data;
    console.error("OAuth validation error:", {
      status,
      data,
      message: err.message
    });
    return { valid: false };
  }
}

async function authMiddleware(req, res, next) {
  try {
    const cookies = req.cookies || {};
    const authHeader = req.headers.authorization;

    const token = cookies.access_token; 

     console.log('Access token extracted:', token);

    if (!token) {
      return res.status(401).json({ message: "Unauthorized: No access token" });
    }

    let decoded = null;
    let tokenType = null;

    // 1. Try OAuth /userinfo endpoint via axios
    const oauthResult = await validateOAuthToken(token);
    if (oauthResult.valid) {
      decoded = oauthResult.user;
      tokenType = "oauth";
    }

    // 2. Try OAUTH_JWT_SECRET (self-contained JWT)
    if (!decoded && OAUTH_JWT_SECRET) {
      try {
        decoded = jwt.verify(token, OAUTH_JWT_SECRET);
        tokenType = "oauth-jwt";
      } catch (err) {
        // Invalid or expired — ignore
      }
    }

    // 3. Try regular JWT_SECRET
    if (!decoded && JWT_SECRET) {
      try {
        decoded = jwt.verify(token, JWT_SECRET);
        tokenType = "regular";
      } catch (err) {
        return res.status(401).json({ message: "Unauthorized: Invalid token" });
      }
    }

    // Final check: must have decoded payload
    if (!decoded) {
      return res.status(401).json({ message: "Unauthorized: Invalid or unsupported token" });
    }

    // Map user fields safely
    req.user = {
      id: decoded.sub || decoded.user_id || decoded.id || decoded.uid,
      email: decoded.email || null,
      username: decoded.username || decoded.preferred_username || decoded.name || null,
      role: decoded.role || "user",
      tenant: decoded.tenant || "default",
      permissions: Array.isArray(decoded.permissions) ? decoded.permissions : []
    };

    console.log(`Authenticated user via ${tokenType}:`, req.user.id);
    next();
  } catch (err) {
    console.error("Authentication middleware error:", err);
    return res.status(500).json({ message: "Internal server error" });
  }
}

module.exports = authMiddleware;