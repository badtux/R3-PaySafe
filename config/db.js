// db.js
const { MongoClient } = require('mongodb');
require('dotenv').config();

const isLive = process.env.LIVE === 'true';
const uri = isLive ? process.env.MONGO_URI_LIVE : process.env.MONGO_URI_DEV;
const client = new MongoClient(uri);


const COMMON_DB_NAME = "DT-Plutos";

async function connectToMongo() {
  try {
    await client.connect();
    console.log(`✅ Connected to MongoDB (${isLive ? 'LIVE' : 'DEV'})`);
  } catch (err) {
    console.error("❌ MongoDB connection error:", err);
    process.exit(1);
  }
}

/**
 * Get tenant-specific payments collection
 * @param {string} tenant
 */
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

/**
 * Get tenant-specific users collection
 * @param {string} tenant
 */
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

function getCommonDB() {
  return client.db(COMMON_DB_NAME);
}
async function getActiveGatewayCollection() {
  const db = getCommonDB();

  const collections = await db.listCollections().toArray();
  const names = collections.map(c => c.name);

  if (!names.includes("active_gateways")) {
    await db.createCollection("active_gateways");
    console.log("📘 Created 'active_gateways' collection");
  }

  return db.collection("active_gateways");
}

module.exports = {
  connectToMongo,
  getPaymentCollection,
  getUserCollection,
  getCommonDB,
  getActiveGatewayCollection
};
