const { MongoClient } = require('mongodb');
require('dotenv').config();

const isLive = process.env.LIVE === 'true';
const uri = isLive ? process.env.MONGO_URI_LIVE : process.env.MONGO_URI_DEV;
const client = new MongoClient(uri);
let paymentCollection;
let userCollection;

async function connectToMongo() {
    try {
        await client.connect();
        console.log(`Connected to MongoDB (${isLive ? 'LIVE' : 'DEV'})`);
        const db = client.db('malkey_paysafe');
        paymentCollection = db.collection('payments');
        userCollection = db.collection('users');
        const collections = await db.listCollections().toArray();
        console.log('Collections in malkey_paysafe database:', collections.map(c => c.name));
    } catch (error) {
        console.error('Error connecting to MongoDB:', error);
        process.exit(1);
    }
}

module.exports = {
    connectToMongo,
    getPaymentCollection: () => paymentCollection,
    getUserCollection: () => userCollection
};