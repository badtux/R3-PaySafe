const { MongoClient } = require('mongodb');
require('dotenv').config();

const isLive = process.env.LIVE === 'true';
const uri = isLive ? process.env.MONGO_URI_LIVE : process.env.MONGO_URI_DEV;
const client = new MongoClient(uri);

let malkeyDb, seylanDb;
let paymentCollectionMalkey, paymentCollectionSeylan, userCollectionMalkey, userCollectionSeylan;

async function connectToMongo() {
    try {
        await client.connect();
        console.log(`Connected to MongoDB (${isLive ? 'LIVE' : 'DEV'})`);

        // Initialize malkey_paysafe database
        malkeyDb = client.db(process.env.MONGO_DB_MALKEY);
        const malkeyCollections = await malkeyDb.listCollections().toArray();
        const malkeyCollectionNames = malkeyCollections.map(c => c.name);

        if (!malkeyCollectionNames.includes('payments')) {
            await malkeyDb.createCollection('payments');
            console.log("Created 'payments' collection in malkey_paysafe");
        }
        if (!malkeyCollectionNames.includes('users')) {
            await malkeyDb.createCollection('users');
            console.log("Created 'users' collection in malkey_paysafe");
        }

        paymentCollectionMalkey = malkeyDb.collection('payments');
        userCollectionMalkey = malkeyDb.collection('users');
        console.log('Collections in malkey_paysafe database:', malkeyCollectionNames);

        // Initialize seylan_paysafe database
        seylanDb = client.db(process.env.MONGO_DB_SEYLAN);
        const seylanCollections = await seylanDb.listCollections().toArray();
        const seylanCollectionNames = seylanCollections.map(c => c.name);

        if (!seylanCollectionNames.includes('payments')) {
            await seylanDb.createCollection('payments');
            console.log("Created 'payments' collection in seylan_paysafe");
        }
        if (!seylanCollectionNames.includes('users')) {
            await seylanDb.createCollection('users');
            console.log("Created 'users' collection in seylan_paysafe");
        }

        paymentCollectionSeylan = seylanDb.collection('payments');
        userCollectionSeylan = seylanDb.collection('users');
        console.log('Collections in seylan_paysafe database:', seylanCollectionNames);

    } catch (error) {
        console.error('Error connecting to MongoDB:', error);
        process.exit(1);
    }
}

function getPaymentCollection(dbName) {
    if (dbName === 'malkey') {
        return paymentCollectionMalkey;
    } else if (dbName === 'helpage') {
        return paymentCollectionSeylan;
    }
    throw new Error('Invalid dbName parameter');
}

function getUserCollection(dbName) {
    if (dbName === 'malkey') {
        return userCollectionMalkey;
    } else if (dbName === 'helpage') {
        return userCollectionSeylan;
    }
    throw new Error('Invalid dbName parameter');
}

module.exports = {
    connectToMongo,
    getPaymentCollection,
    getUserCollection
};