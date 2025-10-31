
const express = require("express");
const cors = require("cors");
const path = require("path");
const fs = require('fs');
const https = require('https');
const { connectToMongo } = require("./config/db");
const paymentRoutes = require("./routes/paymentRoutes");
const pdfRoutes = require("./routes/receiptRoutes");
const gatewayRoutes = require("./routes/settingRouters");
const { saveHardcodedGateways } = require("./services/setting.service");

require("dotenv").config();

const PORT = process.env.PORT || 3008;
const APP_FQDN = process.env.APP_FQDN;
const LIVE = process.env.LIVE === 'true';


const app = express();

app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(express.static("public"));


const allowedOrigins = [
  /^https:\/\/([a-zA-Z0-9-]+)\.go\.digitable\.io(:3008)?$/,
  /^http:\/\/([a-zA-Z0-9-]+)\.localhost:3008$/,
  /^http:\/\/127\.0\.0\.1:5501\/?$/
];

const corsOptions = {
  origin: function (origin, callback) {
    console.log("CORS Origin:", origin);
    if (
      !origin ||
      allowedOrigins.some((pattern) =>
        pattern instanceof RegExp ? pattern.test(origin) : pattern === origin
      )
    ) {
      callback(null, true);
    } else {
      console.log("❌ Blocked by CORS:", origin);
      callback(new Error("Not allowed by CORS"));
    }
  },
  credentials: true,
};

app.use(cors(corsOptions));
// app.use(cors({
//     origin: function (origin, callback) {
//         console.log("CORS Origin:", origin);
//         if (!origin ||
//             origin.match(/^http:\/\/([a-zA-Z0-9-]+)\.localhost:3008$/) ||
//             origin.match(/^https:\/\/([a-zA-Z0-9-]+)\.go\.digitable\.io$/)) {
//             callback(null, true);
//         } else {
//             callback(new Error("Not allowed by CORS"));
//         }
//     },
//     credentials: true,
// }));

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

async function startServer() {
    try {
        await connectToMongo();
        await saveHardcodedGateways();

        if (LIVE) {
            const key = fs.readFileSync(__dirname + '/../../certs/privkey.pem');
            const cert = fs.readFileSync(__dirname + '/../../certs/fullchain.pem');
            const options = {
                key: key,
                cert: cert,
            };

            https.createServer(options, app).listen(PORT, () => {
                console.log(`Server running at https://${APP_FQDN}:${PORT}`);
            });
        } else {
            app.listen(PORT, () => {
                console.log(`Node.js backend listening at http://${APP_FQDN}:${PORT}`);
                console.log('Ensure your .env file is configured correctly.');
            });
        }
    } catch (err) {
        console.error("Failed to start server:", err);
    }
}

startServer();