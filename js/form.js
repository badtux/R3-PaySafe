function simplifyResponseHandler(data) {
  var $paymentForm = $("#paymentForm");
  $(".error").remove();
  if (data.error) {
    if (data.error.code === "validation") {
      var fieldErrors = data.error.fieldErrors;
      fieldErrors.forEach(function (fieldError) {
        $paymentForm.after(
          `<div class='error'> Card number is invalid. Please enter a valid card number.</div>`
        );
      });
    }
    $("#submit").removeAttr("disabled");
  } else {
    $paymentForm.append(
      `<input type='hidden' name='simplifyToken' value='${data.id}' />`
    );
    $paymentForm.get(0).submit();
  }
}

$(document).ready(function () {
  $("#paymentForm").attr("novalidate", "novalidate");

  // Form submission handler
  $("#paymentForm").on("submit", function (e) {
    e.preventDefault();
    $("#submit").attr("disabled", "disabled");
    if (!validateRequiredFields()) {
      return;
    }
    if (validateForm()) {
      $("#submit").removeAttr("disabled");
      return;
    }
    const rawCardNumber = $("#card_number").val().replace(/\D/g, "");
    const currency = $("#currency").val();
    let pubkey = currency === "LKR" ? pubkey_lkr : pubkey_usd;

    SimplifyCommerce.generateToken(
      {
        key: pubkey, //live
        card: {
          number: rawCardNumber,
          cvc: $("#cvv").val(),
          expMonth: $("#cc-exp-month").val(),
          expYear: $("#cc-exp-year").val(),
        },
      },
      simplifyResponseHandler
    );
  });

  $("input, select").on("focus", function () {
    let field = $(this);
    if (field.is("[required]") && field.val().trim() === "") {
      showRequiredErrorMessage(field);
    }
    if (field.is("#email")) {
      validateEmail(field.val());
    } else if (field.is("#card_number")) {
      validateCardNumber(field.val().replace(/\D/g, ""));
    } else if (field.is("#expiry-date")) {
      validateExpiryDate(field.val());
    } else if (field.is("#cvv")) {
      validateCVV(field.val());
    } else if (field.is("#amount")) {
      validateAmount(field.val());
    } else if (field.is("#cc-exp-month, #cc-exp-year")) {
      validateExpiryDate();
    }
  });

  $("input, select").on("input", function () {
    let field = $(this);
    if (
      field.is("[required]") &&
      field.val().trim() !== "" &&
      !(field.val() === "MM" || field.val() === "YY")
    ) {
      hideRequiredErrorMessage(field);
    } else if (
      field.is("[required]") &&
      ((field.val().trim() === "" && field.val() === "MM") ||
        field.val() === "YY")
    ) {
      showRequiredErrorMessage(field);
    }

    if (field.is("#email")) {
      validateEmail(field.val());
    } else if (field.is("#card_number")) {
      validateCardNumber(field.val().replace(/\D/g, ""));
    } else if (field.is("#expiry-date")) {
      validateExpiryDate(field.val());
    } else if (field.is("#cvv")) {
      validateCVV(field.val());
    } else if (field.is("#amount")) {
      validateAmount(field.val());
    }
  });
  function validateRequiredFields() {
    let isValid = true;

    $("input[required]").each(function () {
      let field = $(this);
      if (field.val().trim() === "") {
        showRequiredErrorMessage(field);
        isValid = false;
      } else {
        hideRequiredErrorMessage(field);
      }
    });
    $("select[required]").each(function () {
      let field = $(this);
      let value = field.val();

      if (!value || value === "MM" || value === "YY") {
        showRequiredErrorMessage(field);
        isValid = false;
      } else {
        hideRequiredErrorMessage(field);
      }
    });

    return isValid;
  }
  function showRequiredErrorMessage(field) {
    const messageElement = field.next(".error-message");
    messageElement.text("required").css("color", "red");
    field.addClass("invalid");
  }
  function hideRequiredErrorMessage(field) {
    const messageElement = field.next(".error-message");
    messageElement.text("").css("color", "");
    field.removeClass("invalid");
  }

  function validateForm() {
    let isValid = true;

    if (!validateEmail($("#email").val())) {
      isValid = false;
    }
    if (!validateCardNumber($("#card_number").val().replace(/\D/g, ""))) {
      isValid = false;
    }
    if (!validateExpiryDate($("#cc-exp-year").val())) {
      isValid = false;
    }
    if (!validateExpiryDate($("#cc-exp-month").val())) {
      isValid = false;
    }

    if (!validateCVV($("#cvv").val())) {
      isValid = false;
    }
    if (!validateAmount($("#price").val())) {
      isValid = false;
    }
    return isValid;
  }

  $("#card_number").on("input", function (e) {
    let inputVal = $(this).val();
    inputVal = inputVal.replace(/\D/g, "");
    let formattedVal = inputVal.replace(/(\d{4})(?=\d)/g, "$1 ").trim();
    $(this).val(formattedVal);
    validateCardNumber(formattedVal.replace(/\D/g, ""));
  });

  // Email validation function
  function validateEmail(value) {
    const messageElement = $("#emailError");
    const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

    if (emailPattern.test(value)) {
      messageElement.text("Valid email").css("color", "green");
      $("#email").removeClass("invalid").addClass("valid");
    } else {
      messageElement.text("Invalid email").css("color", "red");
      $("#email").removeClass("valid").addClass("invalid");
    }
  }
  // Card number validation function
  function validateCardNumber(value) {
    const messageElement = $("#card_number_msg");
    if (value.length === 16) {
      messageElement.text("Valid digits count").css("color", "green");
      $("#card_number").removeClass("invalid").addClass("valid");
    } else {
      messageElement.text("Enter 16 digits card number").css("color", "red");
      $("#card_number").removeClass("valid").addClass("invalid");
    }
  }
  // Expiry date validation
  function validateExpiryDate(value) {
    const messageElement = $("#expiry_date_msg");

    if (value && value.trim() !== "") {
      const parts = value.split("/");

      if (
        parts.length === 2 &&
        !isNaN(parts[0]) &&
        !isNaN(parts[1]) &&
        parts[0] <= 12
      ) {
        messageElement.text("Valid expiry date").css("color", "green");
        $("#expiry-date").removeClass("invalid").addClass("valid");
        return true;
      } else {
        messageElement.text("Invalid expiry date").css("color", "red");
        $("#expiry-date").removeClass("valid").addClass("invalid");
        return false;
      }
    } else {
      messageElement.text("Expiry date is required").css("color", "red");
      $("#expiry-date").removeClass("valid").addClass("invalid");
      return false;
    }
  }
  // CVV validation
  function validateCVV(value) {
    const messageElement = $("#cvv_msg");
    if (value.length === 3) {
      messageElement.text("Valid CVV").css("color", "green");
      $("#cvv").removeClass("invalid").addClass("valid");
      return true;
    } else {
      messageElement.text("Invalid CVV").css("color", "red");
      $("#cvv").removeClass("valid").addClass("invalid");
      return false;
    }
  }
  // Amount validation
  function validateAmount(value) {
    const messageElement = $("#amount_msg");
    if (!isNaN(value) && value > 0) {
      messageElement.text("Valid amount").css("color", "green");
      $("#amount").removeClass("invalid").addClass("valid");
      return true;
    } else {
      messageElement.text("Invalid amount").css("color", "red");
      $("#amount").removeClass("valid").addClass("invalid");
      return false;
    }
  }
});
