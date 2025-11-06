document.addEventListener("DOMContentLoaded", () => {
  const menuToggle = document.getElementById("menuToggle");
  const dropdownMenu = document.getElementById("dropdownMenu");

  menuToggle.addEventListener("click", () => {
    dropdownMenu.classList.toggle("hidden");
  });

  // Close dropdown when clicking outside
  window.addEventListener("click", (e) => {
    if (!menuToggle.contains(e.target) && !dropdownMenu.contains(e.target)) {
      dropdownMenu.classList.add("hidden");
    }
  });
});
