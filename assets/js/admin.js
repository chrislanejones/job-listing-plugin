document.addEventListener("DOMContentLoaded", function () {
  // Handle refresh jobs button
  const refreshButton = document.getElementById("refresh-jobs-button");
  const statusElement = document.getElementById("refresh-status");

  if (refreshButton) {
    refreshButton.addEventListener("click", async function () {
      refreshButton.disabled = true;
      statusElement.textContent = "Refreshing...";
      statusElement.className = "status-refreshing";

      try {
        const response = await fetch(jobListingAdmin.refreshEndpoint, {
          method: "GET",
          headers: {
            "X-WP-Nonce": jobListingAdmin.nonce,
            "Content-Type": "application/json",
          },
        });

        if (!response.ok) {
          throw new Error(`Error: ${response.status} ${response.statusText}`);
        }

        const data = await response.json();

        if (data.success) {
          statusElement.textContent = `Success! ${data.message}`;
          statusElement.className = "status-success";

          // Update last fetch time if provided
          const lastFetchElement = document.getElementById("last-fetch-time");
          if (lastFetchElement) {
            const now = new Date();
            lastFetchElement.textContent = now.toLocaleString();
          }
        } else {
          throw new Error(data.message || "Unknown error occurred");
        }
      } catch (error) {
        statusElement.textContent = `Failed: ${error.message}`;
        statusElement.className = "status-error";
        console.error("Refresh failed:", error);
      } finally {
        refreshButton.disabled = false;

        // Auto-hide status after 10 seconds
        setTimeout(() => {
          if (statusElement.className !== "status-error") {
            statusElement.textContent = "";
            statusElement.className = "";
          }
        }, 10000);
      }
    });
  }

  // Handle setup form
  const setupButton = document.getElementById("save-setup-button");

  if (setupButton) {
    setupButton.addEventListener("click", async function () {
      // Get form values
      const organizationId = document.getElementById("organization_id").value;
      const scheduleTime1 = document.getElementById("schedule_time_1").value;
      const scheduleTime2 = document.getElementById("schedule_time_2").value;
      const scheduleTime3 = document.getElementById("schedule_time_3").value;
      const responseElement = document.getElementById("setup-response");

      // Validate form
      if (!organizationId) {
        responseElement.textContent = "Organization ID is required";
        responseElement.className = "error-message";
        return;
      }

      if (!scheduleTime1 && !scheduleTime2 && !scheduleTime3) {
        responseElement.textContent = "At least one schedule time is required";
        responseElement.className = "error-message";
        return;
      }

      // Show loading state
      setupButton.disabled = true;
      setupButton.textContent = "Saving...";
      responseElement.textContent = "Setting up schedules...";
      responseElement.className = "info-message";

      // Prepare form data
      const formData = new FormData();
      formData.append("action", "save_job_listing_setup");
      formData.append("nonce", jobListingAdmin.nonce);
      formData.append("organization_id", organizationId);

      if (scheduleTime1) formData.append("schedule_time_1", scheduleTime1);
      if (scheduleTime2) formData.append("schedule_time_2", scheduleTime2);
      if (scheduleTime3) formData.append("schedule_time_3", scheduleTime3);

      try {
        const response = await fetch(jobListingAdmin.ajaxUrl, {
          method: "POST",
          body: formData,
          credentials: "same-origin",
        });

        const result = await response.json();

        if (result.success) {
          responseElement.textContent = result.data.message;
          responseElement.className = "success-message";

          // Redirect after successful setup
          setTimeout(() => {
            window.location.href = result.data.redirect;
          }, 1500);
        } else {
          throw new Error(result.data?.message || "Failed to save setup");
        }
      } catch (error) {
        responseElement.textContent = `Error: ${error.message}`;
        responseElement.className = "error-message";
        console.error("Setup failed:", error);
      } finally {
        setupButton.disabled = false;
        setupButton.textContent = jobListingAdmin.setupComplete
          ? "Update Setup"
          : "Complete Setup";
      }
    });

    // Handle schedule time selection uniqueness
    const timeSelects = document.querySelectorAll('[id^="schedule_time_"]');

    timeSelects.forEach((select) => {
      select.addEventListener("change", () => {
        const selectedValue = select.value;
        if (!selectedValue) return;

        // Check if this time is selected in another dropdown
        timeSelects.forEach((otherSelect) => {
          if (otherSelect !== select && otherSelect.value === selectedValue) {
            alert(
              "You've already selected this time. Please choose a different time."
            );
            select.value = "";
          }
        });
      });
    });
  }
});
