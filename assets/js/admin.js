jQuery(document).ready(function ($) {
  // Add Schedule Time Button
  $("#add-schedule-time").on("click", function () {
    var newInput = `
            <div class="schedule-time-input">
                <input type="time" name="schedule_times[]">
                <button type="button" class="remove-schedule-time">-</button>
            </div>
        `;
    $("#schedule-times-container").append(newInput);
  });

  // Remove Schedule Time Button
  $(document).on("click", ".remove-schedule-time", function () {
    // Ensure at least one time input remains
    if ($(".schedule-time-input").length > 1) {
      $(this).closest(".schedule-time-input").remove();
    }
  });

  // Setup Form Submission
  $("#job-listing-setup-form").on("submit", function (e) {
    e.preventDefault();

    // Validate inputs
    var organizationId = $("#organization-id").val().trim();
    var scheduleTimes = $('input[name="schedule_times[]"]')
      .map(function () {
        return $(this).val().trim();
      })
      .get()
      .filter(Boolean);

    if (!organizationId) {
      alert("Please enter an Organization ID");
      return;
    }

    if (scheduleTimes.length === 0) {
      alert("Please add at least one schedule time");
      return;
    }

    // AJAX Submit
    $.ajax({
      url: jobListingAdmin.ajax_url,
      method: "POST",
      data: {
        action: "job_listing_save_setup",
        nonce: jobListingAdmin.nonce,
        organization_id: organizationId,
        schedule_times: scheduleTimes,
      },
      success: function (response) {
        if (response.success) {
          alert("Settings saved successfully!");
          // Optionally refresh the page or update UI
          location.reload();
        } else {
          alert("Error: " + response.data);
        }
      },
      error: function (xhr) {
        alert("An error occurred: " + xhr.responseJSON.data);
      },
    });
  });

  // Refresh Jobs Button
  $("#refresh-jobs").on("click", function () {
    $.ajax({
      url: jobListingAdmin.ajax_url,
      method: "POST",
      data: {
        action: "job_listing_refresh_jobs",
        nonce: jobListingAdmin.nonce,
      },
      success: function (response) {
        if (response.success) {
          alert("Jobs refreshed successfully! " + response.data.message);
          location.reload();
        } else {
          alert("Error: " + response.data);
        }
      },
      error: function (xhr) {
        alert("An error occurred: " + xhr.responseJSON.data);
      },
    });
  });
});
