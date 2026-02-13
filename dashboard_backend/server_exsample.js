const express = require("express");
const cors = require("cors");
const path = require("path");
const fs = require('fs');
const tls = require('tls');
const https = require('https');
const { connectToMongo } = require("./config/db");
const paymentRoutes = require("./routes/paymentRoutes");
const pdfRoutes = require("./routes/receiptRoutes");
const gatewayRoutes = require("./routes/settingRouters");
const { saveHardcodedGateways } = require("./services/setting.service");
require("dotenv").config();

const PORT = process.env.PORT || 3008;
const LIVE = process.env.LIVE === 'true';

const CERTS_BASE_DIR = path.join(__dirname, '../../certs');

const app = express();

const allowedOrigins = [
  'https://malkey.go.digitable.io',
  'https://helpage.go.digitable.io',
  'http://localhost:3000',
  'http://localhost:5501',
  'http://127.0.0.1:5501'
];

// 1. CLEANED CORS (No more process.env or global mutations here)
const corsOptions = {
  origin: function (origin, callback) {
    if (!origin || allowedOrigins.includes(origin)) {
      return callback(null, true);
    }
    console.warn(`[CORS] Blocked origin: ${origin}`);
    return callback(new Error('Not allowed by CORS'));
  },
  credentials: true,
};

app.use(cors(corsOptions));
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(express.static("public"));

// Request Logger
app.use((req, res, next) => {
  console.log(`\n--- Incoming: ${req.method} ${req.originalUrl} ---`);
  next();
});

app.use("/api/pdf", pdfRoutes);
app.use("/api", paymentRoutes);
app.use('/api/settings', gatewayRoutes);

// 2. HELPER TO GET CERT FOR DOMAIN
const getCertContext = (hostname) => {
  if (!hostname) return null;
  const certDir = path.join(CERTS_BASE_DIR, hostname);
  const keyPath = path.join(certDir, 'privkey.pem');
  const certPath = path.join(certDir, 'fullchain.pem');

  if (fs.existsSync(keyPath) && fs.existsSync(certPath)) {
    try {
      return tls.createSecureContext({
        key: fs.readFileSync(keyPath),
        cert: fs.readFileSync(certPath),
      });
    } catch (err) {
      console.error(`[SSL] Error reading cert for ${hostname}:`, err.message);
      return null;
    }
  }
  return null;
};

// 3. START SERVER LOGIC
async function startServer() {
  try {
    await connectToMongo();
    await saveHardcodedGateways();

    if (LIVE) {
      // Pick a valid default domain from your allowed list for fallback
      const defaultDomain = 'malkey.go.digitable.io'; 
      const defaultContext = getCertContext(defaultDomain);

      if (!defaultContext) {
        console.error(`[FATAL] Required default cert not found for ${defaultDomain}`);
        process.exit(1);
      }

      const httpsOptions = {
        // SNI intercepts the "coming domain" name before the request hits Express
        SNICallback: (domain, cb) => {
          const context = getCertContext(domain);
          if (context) {
            console.log(`[SNI] Serving certificate for: ${domain}`);
            cb(null, context);
          } else {
            console.warn(`[SNI] No cert for ${domain}, using default: ${defaultDomain}`);
            cb(null, defaultContext);
          }
        },
        // Fallback for older clients that don't support SNI
        key: fs.readFileSync(path.join(CERTS_BASE_DIR, defaultDomain, 'privkey.pem')),
        cert: fs.readFileSync(path.join(CERTS_BASE_DIR, defaultDomain, 'fullchain.pem')),
      };

      https.createServer(httpsOptions, app).listen(PORT, () => {
        console.log(` HTTPS Gateway Server running on port ${PORT}`);
      });
    } else {
      app.listen(PORT, () => {
        console.log(` Dev Gateway server running at http://localhost:${PORT}`);
      });
    }
  } catch (err) {
    console.error("Failed to start server:", err);
  }
}

startServer();