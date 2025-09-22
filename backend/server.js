// server.js
const express = require('express');
const cors = require('cors');
const { connectToMongo } = require('./config/db');
const paymentRoutes = require('./routes/paymentRoutes');

const app = express();
const port = 3000;

app.use(cors());
app.use(express.json());

// Mount routes
app.use('/api', paymentRoutes);

// Start the server
async function startServer() {
    await connectToMongo();
    app.listen(port, () => {
        console.log(`Server running on http://localhost:${port}`);
        console.log(`LIVE mode: ${process.env.APP_LIVE === 'true'}`);
    });
}

startServer();