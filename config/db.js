const { MongoClient } = require('mongodb');
require('dotenv').config();

const isLive = process.env.LIVE === 'true';
const uri = isLive ? process.env.MONGO_URI_LIVE : process.env.MONGO_URI_DEV;
const client = new MongoClient(uri);

let malkeyDb, helpageDb;
let paymentCollectionMalkey, paymentCollectionHelpage, userCollectionMalkey, userCollectionHelpage;

async function connectToMongo() {
    try {
        await client.connect();
        console.log(`Connected to MongoDB (${isLive ? 'LIVE' : 'DEV'})`);

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


        helpageDb = client.db(process.env.MONGO_DB_HELPAGE);
        const helpageCollections = await helpageDb.listCollections().toArray();
        const helpageCollectionNames = helpageCollections.map(c => c.name);

        if (!helpageCollectionNames.includes('payments')) {
            await helpageDb.createCollection('payments');
            console.log("Created 'payments' collection in helpage_paysafe");
        }
        if (!helpageCollectionNames.includes('users')) {
            await helpageDb.createCollection('users');
            console.log("Created 'users' collection in helpage_paysafe");
        }

        paymentCollectionHelpage = helpageDb.collection('payments');
        userCollectionHelpage = helpageDb.collection('users');
        console.log('Collections in helpage_paysafe database:', helpageCollectionNames);

    } catch (error) {
        console.error('Error connecting to MongoDB:', error);
        process.exit(1);
    }
}

function getPaymentCollection(dbName) {
    if (dbName === 'malkey') {
        return paymentCollectionMalkey;
    } else if (dbName === 'helpage') {
        return paymentCollectionHelpage;
    }
    throw new Error('Invalid dbName parameter');
}

function getUserCollection(dbName) {
    if (dbName === 'malkey') {
        return userCollectionMalkey;
    } else if (dbName === 'helpage') {
        return userCollectionHelpage;
    }
    throw new Error('Invalid dbName parameter');
}

module.exports = {
    connectToMongo,
    getPaymentCollection,
    getUserCollection
};