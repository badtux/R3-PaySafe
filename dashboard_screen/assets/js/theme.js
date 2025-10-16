
 function applyTheme(theme) {
      if (theme === "dark") {
        $("#body").addClass("bg-gray-800 text-white");
        $(".bg-white").addClass("bg-gray-900").removeClass("bg-white");
        $(".text-gray-700").addClass("text-gray-200").removeClass("text-gray-700");
        $(".bg-gray-50").addClass("bg-gray-700").removeClass("bg-gray-50");
        $(".border-gray-200").addClass("border-gray-600").removeClass("border-gray-200");
        $(".text-gray-500").addClass("text-gray-300").removeClass("text-gray-500");
        $(".bg-gray-100").addClass("bg-gray-600").removeClass("bg-gray-100");
        $(".hover\\:bg-gray-100").addClass("hover:bg-gray-500").removeClass("hover:bg-gray-100");
        $("#footerText").addClass("text-gray-300").removeClass("text-gray-500");
        $("#activeGatewaysDisplay span").addClass("bg-gray-700").removeClass("bg-gray-200");
      } else {
        $("#body").removeClass("bg-gray-800 text-white");
        $(".bg-gray-900").addClass("bg-white").removeClass("bg-gray-900");
        $(".text-gray-200").addClass("text-gray-700").removeClass("text-gray-200");
        $(".bg-gray-700").addClass("bg-gray-50").removeClass("bg-gray-700");
        $(".border-gray-600").addClass("border-gray-200").removeClass("border-gray-600");
        $(".text-gray-300").addClass("text-gray-500").removeClass("text-gray-300");
        $(".bg-gray-600").addClass("bg-gray-100").removeClass("bg-gray-600");
        $(".hover\\:bg-gray-500").addClass("hover:bg-gray-100").removeClass("hover:bg-gray-500");
        $("#footerText").addClass("text-gray-500").removeClass("text-gray-300");
        $("#activeGatewaysDisplay span").addClass("bg-gray-200").removeClass("bg-gray-700");
      }
    }