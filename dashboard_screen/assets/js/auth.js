// async function handleOAuthCallback(code, state) {
//   const storedState = localStorage.getItem('oauth_state');
//   const codeVerifier = localStorage.getItem('oauth_code_verifier');
//   const tenant = localStorage.getItem('oauth_tenant');

//   // Clear temporary OAuth data from storage
//   localStorage.removeItem('oauth_state');
//   localStorage.removeItem('oauth_code_verifier');
//   localStorage.removeItem('oauth_tenant');

//   const clientId = `${tenant || 'malkey'}_humanv2`;
//   const redirectUri = `${window.location.origin}/dashboard_screen/auth/callback.html`;
//   const tokenUrl = 'https://accounts.go.digitable.io/token';

//   const bodyParams = new URLSearchParams({
//     grant_type: 'authorization_code',
//     client_id: clientId,
//     code: code,
//     redirect_uri: redirectUri,
//     code_verifier: codeVerifier
//   });

//   try {
//     const response = await fetch(tokenUrl, {
//       method: 'POST',
//       headers: {
//         'Content-Type': 'application/x-www-form-urlencoded'
//       },
//       body: bodyParams.toString()
//     });

//     console.log("🔄 Token Exchange Response:", response);

//     if (!response.ok) {
//       throw new Error('Token exchange failed');
//     }

//     const tokens = await response.json();
//     console.log("✅ Tokens received:", tokens);

//     localStorage.setItem('authToken', tokens.access_token);
//     localStorage.setItem('refreshToken', tokens.refresh_token);

//     const user = parseJwt(tokens.id_token);
//     localStorage.setItem('oauth_user', JSON.stringify(user));
//     localStorage.setItem('userId', user.sub || user.id);
//     localStorage.setItem('userRole', user.role || 'user');

//     const userTenant = user.tenant || tenant || 'default';
//     localStorage.setItem('tenant', userTenant);
//     localStorage.setItem('isAuthenticated', 'true');

//     const hostname = window.location.hostname;
//     const IS_LOCAL = hostname === 'localhost' || hostname === '127.0.0.1';
//     const BASE_URL = IS_LOCAL
//       ? `http://${userTenant}.localhost:3008/api`
//       : `https://${userTenant}.go.digitable.io:3008/api`;

//     localStorage.setItem('BASE_URL', BASE_URL);
//     console.log("✅ Login successful. BASE_URL:", BASE_URL);

//     window.location.href = "/index.html";

//   } catch (error) {
//     console.error("❌ OAuth callback error:", error);
//     document.body.innerHTML = `<h3 style="color:red;">OAuth failed. Check console for details.</h3>`;
//   }
// }

// function parseJwt(token) {
//   const base64Url = token.split('.')[1];
//   const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
//   const jsonPayload = decodeURIComponent(atob(base64).split('').map(function(c) {
//     return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
//   }).join(''));
//   return JSON.parse(jsonPayload);
// }
