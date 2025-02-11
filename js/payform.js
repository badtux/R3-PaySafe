$(document).ready(function () {
  $("#paymentForm").attr("novalidate", "novalidate");

  // Enable the submit button when the checkbox is checked
  $("#tos").on("change", function () {
    if ($(this).is(":checked")) {
      $('#paymentForm input[type="submit"]').prop("disabled", false);
    } else {
      $('#paymentForm input[type="submit"]').prop("disabled", true);
    }
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
    value = value.replace(/\D/g, '');
    if (value.length === 15 && (value.startsWith('34') || value.startsWith('37'))) {
        if (luhnCheck(value)) {
            messageElement.text("Valid Amex Card Number").css("color", "green");
            $("#card_number").removeClass("invalid").addClass("valid");
        } else {
            messageElement.text("Invalid Amex card number").css("color", "red");
            $("#card_number").removeClass("valid").addClass("invalid");
        }
    } else {
        messageElement.text("Enter a 15-digit valid Amex card number").css("color", "red");
        $("#card_number").removeClass("valid").addClass("invalid");
    }
}

function luhnCheck(value) {
    let sum = 0;
    let shouldDouble = false;
    for (let i = value.length - 1; i >= 0; i--) {
        let digit = parseInt(value.charAt(i), 10);

        if (shouldDouble) {
            digit *= 2;
            if (digit > 9) {
                digit -= 9;
            }
        }

        sum += digit;
        shouldDouble = !shouldDouble;
    }

    return sum % 10 === 0;
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
    if (value.length === 4) {
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

  $("#paymentForm").on("submit", function (event) {
    event.preventDefault();

    $("#submit").attr("disabled", "disabled");
    if (!validateRequiredFields()) {
      return;
    }
    if (validateForm()) {
      $("#submit").removeAttr("disabled");
      return;
    }

       const $submitBtn = $(this).find('[type="submit"]');
      const originalText = $submitBtn.val();
  
      // Disable button during processing
      $submitBtn.prop('disabled', true).val('Processing...');
  
      // Prepare form data
      const formData = {
          card_number: $('#card_number').val().replace(/\D/g, ''),
          exp_month: $('#cc-exp-month').val(),
          exp_year: $('#cc-exp-year').val(),
          cvv: $('#cvv').val(),
          name: $('#name').val(),
          email: $('#email').val(),
          price: $('#price').val(),
          currency: $('#currency').val(),
          reference: $('#reference').val()
      };
  
      // AJAX request
      $.ajax({
          url: '/auth.php',
          method: 'POST',
          data: formData,
          dataType: 'json',
          success: function(responseData) {
              if (responseData.redirect) {
                  window.location.href = responseData.redirect;
              } else {
                  // Handle success notification
                  $('#maincontainer').hide();
                  $('#notificationMessage').removeClass('hidden');
                  $('#messageText').text(responseData.message)
                      .toggleClass('text-green-500', responseData.status === 'APPROVED')
                      .toggleClass('text-red-500', responseData.status !== 'APPROVED');
              }
          },
          error: function(xhr) {
              // Handle error response
              const error = xhr.responseJSON || {};
              $('#maincontainer').hide();
              $('#notificationMessage').removeClass('hidden');
              $('#messageText').text(error.message || 'Payment processing failed')
                  .addClass('text-red-500');
          },
          complete: function() {
              // Re-enable button
              $submitBtn.prop('disabled', false).val(originalText);
          }
      });
  })
})

;