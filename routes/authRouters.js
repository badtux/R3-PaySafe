const express = require('express');
const router = express.Router();
const { register, login, logout, checkSession } = require('../controllers/authController');

router.post(':db/register', register);
router.post(':db/login', login);
router.get(':db/logout', logout);
router.get(':db/check-session', checkSession);

module.exports = router;