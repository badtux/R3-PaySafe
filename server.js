const express = require("express");
const cors = require("cors");
const path = require("path");
const { connectToMongo } = require("./config/db");
const paymentRoutes = require("./routes/paymentRoutes");
const pdfRoutes = require("./routes/receiptRoutes");
require("dotenv").config();

const app = express();
const port = 3008;

// Middleware
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(express.static("public"));

const allowedOrigins = [
  "http://localhost:3008",
  "https://malkey.go.digitable.io",
];

app.use(
  cors({
    origin: function (origin, callback) {
      console.log("CORS Origin:", origin);
      if (!origin || allowedOrigins.includes(origin)) {
        callback(null, true);
      } else {
        callback(new Error("Not allowed by CORS"));
      }
    },
    credentials: true,
  })
);


app.get("/", (req, res) => {
  res.sendFile(path.join(__dirname, "public", "index.html"));
});


app.get("/dashboard.html", (req, res) => {
  res.sendFile(path.join(__dirname, "public", "index.html"));
});

app.use("/api/pdf", pdfRoutes);
app.use("/api", paymentRoutes);


async function startServer() {
  await connectToMongo();
  app.listen(port, () => {
    console.log(`Server running at http://localhost:${port}`);
    console.log(`LIVE mode: ${process.env.LIVE === "true"}`);
  });
}

startServer();
