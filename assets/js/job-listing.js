(function ($) {
  "use strict";

  class JobListing {
    constructor(container) {
      this.container = container;
      this.jobsList = container.find(".jobs-list");
      this.loading = container.find(".loading");
      this.settings = {
        showDepartment: container.data("show-department") === "yes",
        showLocation: container.data("show-location") === "yes",
        jobsPerPage: container.data("jobs-per-page") || 10,
      };

      this.init();
    }

    init() {
      this.loadJobs();
      this.bindEvents();
    }

    bindEvents() {
      // Add any event listeners here
      $(window).on("resize", this.handleResize.bind(this));
    }

    handleResize() {
      // Handle responsive behavior if needed
    }

    async loadJobs() {
      try {
        const response = await $.ajax({
          url: jobListingData.ajaxUrl,
          method: "GET",
          beforeSend: (xhr) => {
            xhr.setRequestHeader("X-WP-Nonce", jobListingData.nonce);
          },
        });

        this.loading.hide();

        if (response && response.jobs) {
          this.renderJobs(response.jobs);
        } else {
          this.showError("No jobs found");
        }
      } catch (error) {
        console.error("Error loading jobs:", error);
        this.showError("Error loading jobs. Please try again later.");
      }
    }

    renderJobs(jobs) {
      // Clear existing jobs
      this.jobsList.empty();

      // Limit jobs based on jobsPerPage setting
      const limitedJobs = jobs.slice(0, this.settings.jobsPerPage);

      limitedJobs.forEach((job) => {
        const jobElement = this.createJobElement(job);
        this.jobsList.append(jobElement);
      });

      // Add fade-in animation
      this.jobsList.find(".job-item").each((index, element) => {
        setTimeout(() => {
          $(element).addClass("fade-in");
        }, index * 100);
      });
    }

    createJobElement(job) {
      let jobHtml = `
                <div class="job-item" style="opacity: 0; transition: opacity 0.3s ease">
                    <h3>${this.escapeHtml(job.title)}</h3>`;

      if (this.settings.showDepartment && job.department) {
        jobHtml += `
                    <p class="job-department">
                        <i class="fas fa-briefcase"></i>
                        ${this.escapeHtml(job.department)}
                    </p>`;
      }

      if (this.settings.showLocation && job.location) {
        jobHtml += `
                    <p class="job-location">
                        <i class="fas fa-map-marker-alt"></i>
                        ${this.escapeHtml(job.location)}
                    </p>`;
      }

      jobHtml += `
                    <a href="${this.escapeHtml(job.applicationUrl)}" 
                       class="job-apply-button" 
                       target="_blank"
                       rel="noopener noreferrer">
                        Apply Now
                    </a>
                </div>`;

      return jobHtml;
    }

    showError(message) {
      this.loading.hide();
      this.jobsList.html(`
                <div class="error-message">
                    ${this.escapeHtml(message)}
                </div>
            `);
    }

    escapeHtml(unsafe) {
      return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    }
  }

  // Initialize on document ready
  $(document).ready(() => {
    $(".job-listing-container").each((index, element) => {
      new JobListing($(element));
    });
  });
})(jQuery);
