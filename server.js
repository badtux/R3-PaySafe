const express = require("express");
const cors = require("cors");
const session = require("express-session");
const MongoDBStore = require("connect-mongodb-session")(session);
const cookieParser = require("cookie-parser");
const path = require("path");
const { connectToMongo } = require("./config/db");
const paymentRoutes = require("./routes/paymentRoutes");
const authRoutes = require("./routes/authRouters");

const app = express();
const port = 3008;

require("dotenv").config();

// Middleware
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(cookieParser());
app.use(express.static("public"));

// CORS
app.use(
  cors({
    origin: (origin, callback) => {
      const allowedOrigins = [
        "http://localhost:3008",
        "https://malkey.go.digitable.io",
      ];
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

// Session Store
const store = new MongoDBStore({
  uri:
    process.env.LIVE === "true"
      ? process.env.MONGO_URI_LIVE
      : process.env.MONGO_URI_DEV,
  collection: "sessions",
});

store.on("error", (error) => console.error("Session store error:", error));
store.on("connected", () => console.log("MongoDB session store connected"));

// Session middleware
app.use(
  session({
    secret: process.env.SESSION_SECRET || "your-secret-key",
    resave: false,
    saveUninitialized: false,
    store: store,
    cookie: {
      maxAge: 1000 * 60 * 60 * 24, 
      secure: process.env.LIVE === "true",
      sameSite: "lax",
      path: "/",
    },
  })
);

const isAuthenticated = (req, res, next) => {
  console.log("Request URL:", req.url);
  console.log("Session ID:", req.sessionID);
  console.log("Cookies:", req.cookies);
  console.log("Session:", req.session);
  console.log("Session User:", req.session.user);
  if (req.session.user) return next();
  res.status(401).json({ message: "Unauthorized" });
};


app.get("/", (req, res) => {
  if (req.session.user) {
    res.sendFile(path.join(__dirname, "public", "dashboard.html"));
  } else {
    res.sendFile(path.join(__dirname, "public", "login.html"));
  }
});

app.use("/api/auth", authRoutes);
app.use("/api", isAuthenticated, paymentRoutes); 

// Start server
async function startServer() {
  await connectToMongo();
  app.listen(port, () => {
    console.log(`Server running on http://localhost:${port}`);
    console.log(`LIVE mode: ${process.env.LIVE === "true"}`);
  });
}

startServer();
