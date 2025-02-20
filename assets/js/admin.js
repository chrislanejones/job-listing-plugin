document.addEventListener("DOMContentLoaded", function () {
  // Handle hour button selection
  const hourButtons = document.querySelectorAll(".hour-button");
  const selectedHoursDisplay = document.getElementById(
    "selected-hours-display"
  );
  const scheduleError = document.getElementById("schedule-error");
  const form = document.getElementById("job-listing-setup-form");
  let selectedHours = [];

  // Initialize selected hours from existing inputs
  document.querySelectorAll(".selected-hour-input").forEach((input) => {
    const hour = input.value.split(":")[0];
    selectedHours.push(hour);
  });

  function updateSelectedHours() {
    // Update the display
    selectedHoursDisplay.textContent = selectedHours
      .map((hour) => `${hour}:00`)
      .join(", ");

    // Update hidden inputs
    const container = document.querySelector(".selected-hours-container");

    // Remove existing inputs
    document.querySelectorAll(".selected-hour-input").forEach((input) => {
      input.remove();
    });

    // Add new inputs
    selectedHours.forEach((hour) => {
      const input = document.createElement("input");
      input.type = "hidden";
      input.name = "schedule_times[]";
      input.value = `${hour}:00`;
      input.className = "selected-hour-input";
      container.appendChild(input);
    });

    // Validate
    if (selectedHours.length < 3) {
      scheduleError.textContent = "Please select exactly 3 hours";
      return false;
    } else {
      scheduleError.textContent = "";
      return true;
    }
  }

  hourButtons.forEach((button) => {
    button.addEventListener("click", function () {
      const hour = this.dataset.hour;

      if (this.classList.contains("selected")) {
        // Deselect
        this.classList.remove("selected");
        selectedHours = selectedHours.filter((h) => h !== hour);
      } else {
        // Select
        if (selectedHours.length >= 3) {
          // If we already have 3 selected, remove the first one
          const oldestHour = selectedHours.shift();
          document
            .querySelector(`.hour-button[data-hour="${oldestHour}"]`)
            .classList.remove("selected");
        }

        this.classList.add("selected");
        selectedHours.push(hour);

        // Sort hours chronologically
        selectedHours.sort();
      }

      updateSelectedHours();
    });
  });

  // Form submission validation
  form.addEventListener("submit", function (e) {
    if (selectedHours.length !== 3) {
      e.preventDefault();
      scheduleError.textContent = "Please select exactly 3 hours";
      scheduleError.scrollIntoView({ behavior: "smooth" });
      return;
    }

    // If validation passes, handle form submission
    e.preventDefault();
    saveSetup();
  });

  // Handle save settings
  function saveSetup() {
    const submitButton = document.getElementById("submit");
    const organizationId = document.getElementById("organization-id").value;
    const statusElement = document.createElement("span");
    statusElement.id = "save-status";
    submitButton.parentNode.insertBefore(
      statusElement,
      submitButton.nextSibling
    );

    statusElement.textContent = "Saving...";
    statusElement.className = "status-saving";
    submitButton.disabled = true;

    // Prepare form data
    const formData = new FormData();
    formData.append("action", "job_listing_save_setup");
    formData.append("nonce", jobListingAdmin.nonce);
    formData.append("organization_id", organizationId);

    // Add selected hours
    selectedHours.forEach((hour) => {
      formData.append("schedule_times[]", `${hour}:00`);
    });

    // Send AJAX request
    fetch(jobListingAdmin.ajax_url, {
      method: "POST",
      body: formData,
      credentials: "same-origin",
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          statusElement.textContent = "Settings saved successfully!";
          statusElement.className = "status-success";
          setTimeout(() => {
            location.reload();
          }, 1000);
        } else {
          throw new Error(data.data || "Unknown error occurred");
        }
      })
      .catch((error) => {
        statusElement.textContent = `Error: ${error.message}`;
        statusElement.className = "status-error";
        console.error("Save failed:", error);
      })
      .finally(() => {
        submitButton.disabled = false;
      });
  }

  // Handle refresh jobs button
  const refreshButton = document.getElementById("refresh-jobs");
  if (refreshButton) {
    refreshButton.addEventListener("click", function () {
      const statusElement = document.createElement("span");
      statusElement.id = "refresh-status";
      refreshButton.parentNode.insertBefore(
        statusElement,
        refreshButton.nextSibling
      );

      refreshButton.disabled = true;
      statusElement.textContent = "Refreshing...";
      statusElement.className = "status-refreshing";

      // Send AJAX request
      const formData = new FormData();
      formData.append("action", "job_listing_refresh_jobs");
      formData.append("nonce", jobListingAdmin.nonce);

      fetch(jobListingAdmin.ajax_url, {
        method: "POST",
        body: formData,
        credentials: "same-origin",
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            statusElement.textContent = `Success! ${
              data.data ? data.data.message || "" : ""
            }`;
            statusElement.className = "status-success";

            // Update last fetch time
            const lastFetchElement = document.querySelector(
              "#last-fetch-info p:first-child strong"
            );
            if (lastFetchElement) {
              const now = new Date();
              lastFetchElement.textContent =
                "Last Fetch: " + now.toLocaleString();
            }
          } else {
            throw new Error(data.data || "Failed to refresh jobs");
          }
        })
        .catch((error) => {
          statusElement.textContent = `Failed: ${error.message}`;
          statusElement.className = "status-error";
          console.error("Refresh failed:", error);
        })
        .finally(() => {
          refreshButton.disabled = false;

          // Auto-hide success message after 10 seconds
          setTimeout(() => {
            if (statusElement.className === "status-success") {
              statusElement.remove();
            }
          }, 10000);
        });
    });
  }
});
