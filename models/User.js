const { getUserCollection } = require('../config/db');

// Expected document structure for reference
const userSchema = {
    name: String,
    email: { type: String, unique: true },
    password: String,
    createdAt: Date
};

module.exports = {
    getCollection: () => getUserCollection(),
    schema: userSchema
};