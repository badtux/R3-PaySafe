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
  $("#paymentForm").on("submit", function (e) {
    e.preventDefault();
    $("#submit").attr("disabled", "disabled");

    const rawCardNumber = $("#card_number").val().replace(/\D/g, "");

    SimplifyCommerce.generateToken(
      {
        key: "sbpb_NjU0NWMyMjMtMzVmYi00ZWVjLWI0NDItN2I4MjljZWJiM2I0",
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

  $("#email").on("input", function () {
    let value = $(this).val();
    validateEmail(value);
  });

  $("#card_number").on("input", function () {
    let formattedValue = $(this).val().replace(/\D/g, "").slice(0, 16);
    $(this).val(formattedValue.replace(/(\d{4})(?=\d)/g, "$1-"));
    validateCardNumber(formattedValue);
  });

  $("#expiry-date").on("input", function () {
    let value = $(this).val().replace(/\D/g, "").slice(0, 4);
    if (value.length >= 3) {
      $(this).val(value.slice(0, 2) + "/" + value.slice(2, 4));
    } else {
      $(this).val(value);
    }
    validateExpiryDate($(this).val());
  });

  $("#cvv").on("input", function () {
    let value = $(this).val().replace(/\D/g, "").slice(0, 3);
    $(this).val(value);
    validateCVV(value);
  });

  $("#amount").on("input", function () {
    validateAmount($(this).val());
  });

  function validateEmail(value) {
    const messageElement = $("#emailError"); // This is where the validation message will appear
    const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

    if (emailPattern.test(value)) {
      messageElement.text("Valid email address").css("color", "green");
      $("#email").removeClass("invalid").addClass("valid");
    } else {
      messageElement
        .text("Please enter a valid email address")
        .css("color", "red");
      $("#email").removeClass("valid").addClass("invalid");
    }
  }

  $(document).ready(function () {
    $("#email").on("input", function () {
      let value = $(this).val();
      validateEmail(value);
    });
  });

  function validateCardNumber(value) {
    const messageElement = $("#card_number_msg");
    if (value.length === 16) {
      messageElement.text("Valid digits count ").css("color", "green");
      $("#card_number").removeClass("invalid").addClass("valid");
    } else {
      messageElement.text("Enter 16 digits card number").css("color", "red");
      $("#card_number").removeClass("valid").addClass("invalid");
    }
  }

  function validateExpiryDate(value) {
    const messageElement = $("#expiry_date_msg");
    const parts = value.split("/");
    if (
      parts.length === 2 &&
      !isNaN(parts[0]) &&
      !isNaN(parts[1]) &&
      parts[0] <= 12
    ) {
      messageElement.text("Valid expiry date").css("color", "green");
      $("#expiry-date").removeClass("invalid").addClass("valid");
    } else {
      messageElement.text("Invalid expiry date").css("color", "red");
      $("#expiry-date").removeClass("valid").addClass("invalid");
    }
  }

  function validateCVV(value) {
    const messageElement = $("#cvv_msg");
    if (value.length === 3) {
      messageElement.text("Valid CVV").css("color", "green");
      $("#cvv").removeClass("invalid").addClass("valid");
    } else {
      messageElement.text("Invalid CVV").css("color", "red");
      $("#cvv").removeClass("valid").addClass("invalid");
    }
  }

  function validateAmount(value) {
    const messageElement = $("#submit");
    if (!isNaN(value) && value > 0) {
      messageElement.text("Valid amount").css("color", "green");
      $("#amount").removeClass("invalid").addClass("valid");
    } else {
      messageElement.text("Invalid amount").css("color", "red");
      $("#amount").removeClass("valid").addClass("invalid");
    }
  }
});
