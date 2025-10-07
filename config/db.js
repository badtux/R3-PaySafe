// db.js
const { MongoClient } = require('mongodb');
require('dotenv').config();

const isLive = process.env.LIVE === 'true';
const uri = isLive ? process.env.MONGO_URI_LIVE : process.env.MONGO_URI_DEV;
const client = new MongoClient(uri);

async function connectToMongo() {
    try {
        await client.connect();
        console.log(`Connected to MongoDB (${isLive ? 'LIVE' : 'DEV'})`);
    } catch (error) {
        console.error('Error connecting to MongoDB:', error);
        process.exit(1);
    }
}

async function getPaymentCollection(tenant) {
    if (!tenant || typeof tenant !== 'string' || tenant.trim() === '') {
        throw new Error('Invalid tenant name');
    }
    const dbName = `${tenant}_paysafe`;
    const db = client.db(dbName);
    const collections = await db.listCollections().toArray();
    const collectionNames = collections.map(c => c.name);

    if (!collectionNames.includes('payments')) {
        await db.createCollection('payments');
        console.log(`Created 'payments' collection in ${dbName}`);
    }
    return db.collection('payments');
}

async function getUserCollection(tenant) {
    if (!tenant || typeof tenant !== 'string' || tenant.trim() === '') {
        throw new Error('Invalid tenant name');
    }
    const dbName = `${tenant}_paysafe`;
    const db = client.db(dbName);
    const collections = await db.listCollections().toArray();
    const collectionNames = collections.map(c => c.name);

    if (!collectionNames.includes('users')) {
        await db.createCollection('users');
        console.log(`Created 'users' collection in ${dbName}`);
    }
    return db.collection('users');
}

module.exports = {
    connectToMongo,
    getPaymentCollection,
    getUserCollection
};