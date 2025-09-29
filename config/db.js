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

        const existingCollections = await db.listCollections().toArray();
        const collectionNames = existingCollections.map(c => c.name);

        if (!collectionNames.includes('payments')) {
            await db.createCollection('payments');
            console.log("Created 'payments' collection");
        }
        if (!collectionNames.includes('users')) {
            await db.createCollection('users');
            console.log("Created 'users' collection");
        }

        paymentCollection = db.collection('payments');
        userCollection = db.collection('users');

        console.log('Collections in malkey_paysafe database:', collectionNames);

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
