const crypto = require("crypto");
const axios = require("axios");
const { response } = require("express");

function generateState() {
  return crypto.randomBytes(16).toString("hex");
}

function generateVerifier() {
  return crypto.randomBytes(32).toString("base64url");
}

function generateChallenge(verifier) {
  return crypto.createHash("sha256")
    .update(verifier)
    .digest("base64url");
}

async function exchangeCodeForToken(tenant, code, verifier) {
  const response = await axios.post(
    "https://accounts.go.digitable.io/token",
    new URLSearchParams({
      grant_type: "authorization_code",
      code,
      redirect_uri: "http://localhost:3009/api/oauth/callback",
      client_id: `${tenant}_humanv2`,
      code_verifier: verifier,
    }),
    { headers: { "Content-Type": "application/x-www-form-urlencoded" } }
  );
    console.log("Token Response:", response.data); // ✅ log here
  return response.data;
}


async function fetchUserInfo(accessToken) {
  const response = await axios.get("https://accounts.go.digitable.io/userinfo", {
    headers: { Authorization: `Bearer ${accessToken}` },
  });

  return response.data;
}

module.exports = {
  generateState,
  generateVerifier,
  generateChallenge,
  exchangeCodeForToken,
  fetchUserInfo,
};
