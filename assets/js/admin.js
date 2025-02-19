(function ($) {
  "use strict";

  // Utility functions
  const utils = {
    showMessage: function (element, message, type, duration = 10000) {
      element.textContent = message;
      element.className = `status-${type}`;

      if (duration && type !== "error") {
        setTimeout(() => {
          element.textContent = "";
          element.className = "";
        }, duration);
      }
    },

    validateTime: function (time) {
      return /^([01]\d|2[0-3]):([0-5]\d)$/.test(time);
    },

    debounce: function (func, wait) {
      let timeout;
      return function executedFunction(...args) {
        const later = () => {
          clearTimeout(timeout);
          func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
      };
    },
  };

  // Job refresh functionality
  class JobRefresh {
    constructor() {
      this.button = document.getElementById("refresh-jobs-button");
      this.status = document.getElementById("refresh-status");
      this.lastFetchElement = document.getElementById("last-fetch-time");

      if (this.button) {
        this.button.addEventListener("click", this.handleRefresh.bind(this));
      }
    }

    async handleRefresh() {
      if (!this.button || !this.status) return;

      this.button.disabled = true;
      utils.showMessage(this.status, "Refreshing...", "refreshing", 0);

      try {
        const response = await fetch(jobListingAdmin.refreshEndpoint, {
          method: "GET",
          headers: {
            "X-WP-Nonce": jobListingAdmin.nonce,
            "Content-Type": "application/json",
          },
          credentials: "same-origin",
        });

        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.success) {
          utils.showMessage(this.status, `Success! ${data.message}`, "success");
          this.updateLastFetchTime();
        } else {
          throw new Error(data.message || "Unknown error occurred");
        }
      } catch (error) {
        console.error("Refresh failed:", error);
        utils.showMessage(this.status, `Failed: ${error.message}`, "error");
      } finally {
        this.button.disabled = false;
      }
    }

    updateLastFetchTime() {
      if (this.lastFetchElement) {
        const now = new Date();
        const options = {
          year: "numeric",
          month: "long",
          day: "numeric",
          hour: "2-digit",
          minute: "2-digit",
        };
        this.lastFetchElement.textContent = now.toLocaleDateString(
          undefined,
          options
        );
      }
    }
  }

  // Setup form functionality
  class SetupForm {
    constructor() {
      this.button = document.getElementById("save-setup-button");
      this.response = document.getElementById("setup-response");
      this.form = document.querySelector(".job-listing-setup-form");
      this.timeSelects = document.querySelectorAll('[id^="schedule_time_"]');

      if (this.button) {
        this.initializeForm();
      }
    }

    initializeForm() {
      this.button.addEventListener("click", this.handleSubmit.bind(this));
      this.initializeTimeSelects();
      this.addFormValidation();
    }

    initializeTimeSelects() {
      this.timeSelects.forEach((select) => {
        select.addEventListener(
          "change",
          utils.debounce((event) => {
            this.validateTimeSelection(event.target);
          }, 250)
        );
      });
    }

    validateTimeSelection(changedSelect) {
      const selectedValue = changedSelect.value;
      if (!selectedValue) return;

      const duplicateSelect = Array.from(this.timeSelects).find(
        (select) => select !== changedSelect && select.value === selectedValue
      );

      if (duplicateSelect) {
        utils.showMessage(
          this.response,
          "Each time slot must be unique. Please choose a different time.",
          "error"
        );
        changedSelect.value = "";
        changedSelect.focus();
      }
    }

    addFormValidation() {
      this.form.addEventListener(
        "input",
        utils.debounce(() => {
          this.validateForm();
        }, 300)
      );
    }

    validateForm() {
      const organizationId = document.getElementById("organization_id").value;
      const hasScheduleTime = Array.from(this.timeSelects).some(
        (select) => select.value
      );

      this.button.disabled = !organizationId || !hasScheduleTime;
    }

    async handleSubmit() {
      if (!this.validateSubmission()) return;

      this.setLoadingState(true);

      try {
        const formData = this.getFormData();
        const result = await this.submitForm(formData);

        if (result.success) {
          this.handleSuccess(result);
        } else {
          throw new Error(result.data?.message || "Failed to save setup");
        }
      } catch (error) {
        this.handleError(error);
      } finally {
        this.setLoadingState(false);
      }
    }

    validateSubmission() {
      const organizationId = document.getElementById("organization_id").value;
      const hasScheduleTime = Array.from(this.timeSelects).some(
        (select) => select.value
      );

      if (!organizationId || !hasScheduleTime) {
        utils.showMessage(
          this.response,
          "Please fill in all required fields",
          "error"
        );
        return false;
      }

      return true;
    }

    getFormData() {
      const formData = new FormData();
      formData.append("action", "save_job_listing_setup");
      formData.append("nonce", jobListingAdmin.nonce);
      formData.append(
        "organization_id",
        document.getElementById("organization_id").value
      );

      this.timeSelects.forEach((select, index) => {
        if (select.value) {
          formData.append(`schedule_time_${index + 1}`, select.value);
        }
      });

      return formData;
    }

    async submitForm(formData) {
      const response = await fetch(jobListingAdmin.ajaxUrl, {
        method: "POST",
        body: formData,
        credentials: "same-origin",
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      return await response.json();
    }

    handleSuccess(result) {
      utils.showMessage(this.response, result.data.message, "success");

      setTimeout(() => {
        window.location.href = result.data.redirect;
      }, 1500);
    }

    handleError(error) {
      console.error("Setup failed:", error);
      utils.showMessage(this.response, `Error: ${error.message}`, "error");
    }

    setLoadingState(isLoading) {
      this.button.disabled = isLoading;
      this.button.textContent = isLoading
        ? "Saving..."
        : jobListingAdmin.setupComplete
        ? "Update Setup"
        : "Complete Setup";

      if (isLoading) {
        utils.showMessage(this.response, "Setting up schedules...", "info");
      }
    }
  }

  // Initialize when DOM is ready
  document.addEventListener("DOMContentLoaded", function () {
    new JobRefresh();
    new SetupForm();
  });
})(jQuery);
