// app.js
const express = require("express");
const cors = require("cors");
const path = require("path");
const { connectToMongo } = require("./config/db");
const paymentRoutes = require("./routes/paymentRoutes");
const pdfRoutes = require("./routes/receiptRoutes");
require("dotenv").config();

const app = express();
const port = 3008;

app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(express.static("public"));

app.use(cors({
    origin: function (origin, callback) {
        console.log("CORS Origin:", origin);
        if (!origin || 
            origin.match(/^http:\/\/([a-zA-Z0-9-]+)\.localhost:3008$/) || 
            origin.match(/^http:\/\/([a-zA-Z0-9-]+)\.go\.digitable\.io$/)) {
            callback(null, true);
        } else {
            callback(new Error("Not allowed by CORS"));
        }
    },
    credentials: true,
}));


app.get("/", (req, res) => {
    res.sendFile(path.join(__dirname, "public", "index.html"));
});

app.get("/dashboard.html", (req, res) => {
    res.sendFile(path.join(__dirname, "public", "index.html"));
});


app.use("/api/pdf", pdfRoutes);
app.use("/api", paymentRoutes);


async function startServer() {
    try {
        await connectToMongo();
        app.listen(port, () => {
            console.log(`Server running at http://0.0.0.0:${port}`);
            console.log(`LIVE mode: ${process.env.LIVE === "true"}`);
        });
    } catch (err) {
        console.error("Failed to start server:", err);
    }
}

startServer();