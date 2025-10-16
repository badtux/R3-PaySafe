  let BASE_URL;
  const today = new Date().toISOString().split("T")[0];
  const UNIVERSAL_PASSWORD = "admin@123";
  const IS_LOCAL = window.location.hostname.includes("localhost") || window.location.hostname === "127.0.0.1";
  const DEFAULT_TENANT = "malkey";

  $(document).ready(() => {
    console.log("Environment check - Hostname:", window.location.hostname, "Is Local:", IS_LOCAL);

    // --- Get tenant from hostname or localStorage ---
    let tenantFromUrl = IS_LOCAL 
      ? DEFAULT_TENANT 
      : (window.location.hostname.split(".")[0] === "go" ? "" : window.location.hostname.split(".")[0]);

    // Try to load tenant from storage if available
    let tenant = localStorage.getItem("tenant") || tenantFromUrl;
    console.log("Setting tenant to:", tenant);

    $("#username").val(tenant).prop("readonly", true);

    // --- Show/hide password toggle ---
    $("#showPassword").on("change", function() {
      const passwordInput = $("#password");
      passwordInput.attr("type", $(this).is(":checked") ? "text" : "password");
    });

    // --- Login button handler ---
    $("#loginButton").on("click", () => {
      const tenant = $("#username").val().trim();
      const password = $("#password").val();
      console.log("Login attempt - Tenant:", tenant, "Password entered:", !!password);

      if (tenant && password) {
        if (login(tenant, password)) {
          window.location.href = "dashboard.html";
        } else {
          $("#loginError").removeClass("hidden");
        }
      } else {
        $("#loginError").removeClass("hidden");
      }
    });

    // --- Login logic ---
    function login(inputTenant, password) {
      const hostname = window.location.hostname;
      const tenantFromUrl = IS_LOCAL 
        ? DEFAULT_TENANT 
        : (hostname.split(".")[0] === "go" ? "" : hostname.split(".")[0]);

      console.log("Login - Hostname:", hostname, "Tenant from URL:", tenantFromUrl, "Input Tenant:", inputTenant);

      // Prevent mismatch for production tenants
      if (!IS_LOCAL && tenantFromUrl !== "" && inputTenant !== tenantFromUrl) {
        console.log("Tenant mismatch");
        return false;
      }

      // Validate password
      if (password === UNIVERSAL_PASSWORD && inputTenant.trim() !== "") {
        localStorage.setItem("isAuthenticated", "true");
        localStorage.setItem("tenant", inputTenant);

        BASE_URL = IS_LOCAL
          ? `http://${inputTenant}.localhost:3008/api`
          : `https://${inputTenant}.go.digitable.io:3008/api`;

        console.log("Login successful, BASE_URL:", BASE_URL);
        return true;
      }

      console.log("Login failed: Invalid password or tenant");
      return false;
    }
  });