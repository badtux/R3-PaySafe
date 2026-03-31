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
const { startECardCron } = require("./tenantSetting/helpage/eCardCron");
require("dotenv").config();
const PORT = process.env.PORT || 3009;
const APP_FQDN = process.env.APP_FQDN;
const LIVE = process.env.LIVE === 'true';
const CERTS_BASE_DIR = path.join(__dirname, '../../certs');
const app = express();
const allowedOrigins = [
  'https://malkey.go.digitable.io',
  'https://helpage.go.digitable.io',
  'http://localhost:3000',
  'http://localhost:5502',
  'http://127.0.0.1:5502',
];

let CertPath = null;
const corsOptions = {
  origin: function (origin, callback) {
    console.log("CORS Origin:", origin);
    if (!origin) {
      console.log("[CORS] No Origin header present; allowing non-browser request");
      return callback(null, true);
    }
    if (allowedOrigins.includes(origin)) {
      try {
        CertPath = new URL(origin).hostname;
        process.env.CERT_PATH = CertPath;
        global.CERT_PATH_DIR = path.join(CERTS_BASE_DIR, CertPath);
        console.log(`[CORS] Origin matched → CERT_PATH set to: ${CertPath}`);
        return callback(null, true);
      } catch (e) {
        console.error('[CORS] Invalid origin URL:', e.message);
        return callback(new Error('Invalid origin URL'));
      }
    } else {
      console.warn(`[CORS] Blocked origin: ${origin}`);
      return callback(new Error('Not allowed by CORS'));
    }
  },
  credentials: true,
};

app.use(cors(corsOptions));
app.options(/.*/, cors(corsOptions));
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(express.static("public"));

app.use((req, res, next) => {
  const origin = req.headers.origin || 'undefined';
  const fullUrl = `${req.protocol}://${req.get('host')}${req.originalUrl}`;
  console.log(`\n--- Incoming Request ---`);
  console.log(`Full URL: ${fullUrl}`);
  console.log(`Method: ${req.method}`);
  console.log(`Origin: ${origin}`);
  console.log(`-------------------------\n`);
  next();
});

app.use("/api/pdf", pdfRoutes);
app.use("/api", paymentRoutes);
app.use('/api/settings', gatewayRoutes);

app.use((req, res) => {
  console.warn(`[404] No route matched ${req.method} ${req.originalUrl}`);
  res.status(404).json({
    error: "Not Found",
    method: req.method,
    path: req.originalUrl,
  });
});

app.use((err, req, res, next) => {
  console.error("[Unhandled Error]", err);
  if (res.headersSent) {
    return next(err);
  }
  res.status(err.status || 500).json({
    error: err.message || "Internal server error",
  });
});

async function startServer() {
    try {
        await connectToMongo();
        await saveHardcodedGateways();
              startECardCron();

if (LIVE) {
  const getCertForDomain = (hostname) => {
    const certDir = path.join(CERTS_BASE_DIR, hostname);
    const keyPath = path.join(certDir, 'privkey.pem');
    const certPath = path.join(certDir, 'fullchain.pem');
    if (fs.existsSync(keyPath) && fs.existsSync(certPath)) {
      console.log(`[HTTPS] Loaded certificate for ${hostname}`);
      return tls.createSecureContext({
        key: fs.readFileSync(keyPath),
        cert: fs.readFileSync(certPath),
      });
    }
    console.warn(`[HTTPS] No certificate found for ${hostname}, using default.`);
    return null;
  };
  const defaultDomain = process.env.CERT_PATH ;
  const defaultKey = path.join(CERTS_BASE_DIR, defaultDomain, 'privkey.pem');
  const defaultCert = path.join(CERTS_BASE_DIR, defaultDomain, 'fullchain.pem');

  console.log(`Default Key Path: ${defaultKey}`);
  console.log(`Default Cert Path: ${defaultCert}`);
  console.log(`Default domain Path: ${defaultDomain}`);

  if (!fs.existsSync(defaultKey) || !fs.existsSync(defaultCert)) {
    console.error(` Default certificate not found for ${defaultDomain}`);
    process.exit(1);
  }
  const defaultContext = getCertForDomain(defaultDomain);

  const options = {
    SNICallback: (domain, cb) => {
      const context = getCertForDomain(domain);
      cb(null, context || defaultContext);
    },
    key: fs.readFileSync(defaultKey),
    cert: fs.readFileSync(defaultCert),
  };
  https.createServer(options, app).listen(PORT, () => {
    console.log(` HTTPS Server (SNI mode) running on port ${PORT}`);
  });
} else {
  app.listen(PORT, () => {
    console.log(` Development server running at http://localhost:${PORT}`);
  });
}
    } catch (err) {
        console.error("Failed to start server:", err);
    }
}
startServer();