const express = require('express');
const router = express.Router();
const { register, login, logout, checkSession } = require('../controllers/authController');

router.post('/register', register);
router.post('/login', login);
router.get('/logout', logout);
router.get('/check-session', checkSession);

module.exports = router;