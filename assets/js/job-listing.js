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

        if (response && response.jobs && Array.isArray(response.jobs)) {
          if (response.organization) {
            this.renderOrganizationInfo(response.organization);
          }
          this.renderJobs(response.jobs);
        } else {
          this.showError("No jobs found");
        }
      } catch (error) {
        console.error("Error loading jobs:", error);
        this.showError(error.responseJSON?.message || "Error loading jobs");
      }
    }

    renderOrganizationInfo(org) {
      if (org.description || org.values) {
        const orgHtml = `
                    <div class="organization-info">
                        ${
                          org.description
                            ? `<div class="org-description">${org.description}</div>`
                            : ""
                        }
                        ${
                          org.values
                            ? `<div class="org-values">${org.values}</div>`
                            : ""
                        }
                    </div>
                `;
        this.container.prepend(orgHtml);
      }
    }

    renderJobs(jobs) {
      this.jobsList.empty();

      if (jobs.length === 0) {
        this.showMessage("No open positions at this time.");
        return;
      }

      const limitedJobs = jobs.slice(0, this.settings.jobsPerPage);

      limitedJobs.forEach((job) => {
        const jobElement = this.createJobElement(job);
        this.jobsList.append(jobElement);
      });

      this.jobsList.find(".job-item").each((index, element) => {
        setTimeout(() => {
          $(element).addClass("fade-in");
        }, index * 100);
      });
    }

    createJobElement(job) {
      let jobHtml = `
                <div class="job-item">
                    <div class="job-content">
                        <div class="job-header">
                            <h3>${this.escapeHtml(job.title)}</h3>
                            ${
                              job.publishedDate
                                ? `<span class="job-date">Posted ${this.formatDate(
                                    job.publishedDate
                                  )}</span>`
                                : ""
                            }
                        </div>
                        <div class="job-details">`;

      if (this.settings.showDepartment && job.department) {
        jobHtml += `
                    <div class="job-detail">
                        <i class="fas fa-briefcase"></i>
                        ${this.escapeHtml(job.department)}
                    </div>`;
      }

      if (this.settings.showLocation && job.location) {
        jobHtml += `
                    <div class="job-detail">
                        <i class="fas fa-map-marker-alt"></i>
                        ${this.escapeHtml(job.location)}
                    </div>`;
      }

      if (job.workplaceType) {
        jobHtml += `
                    <div class="job-detail">
                        <i class="fas fa-building"></i>
                        ${this.escapeHtml(job.workplaceType)}
                    </div>`;
      }

      if (job.employmentType) {
        jobHtml += `
                    <div class="job-detail">
                        <i class="fas fa-clock"></i>
                        ${this.escapeHtml(job.employmentType)}
                    </div>`;
      }

      if (job.compensation) {
        jobHtml += `
                    <div class="job-detail">
                        <i class="fas fa-dollar-sign"></i>
                        ${this.escapeHtml(job.compensation)}
                    </div>`;
      }

      jobHtml += `
                        </div>
                    </div>
                    <div class="job-action">
                        <a href="${this.escapeHtml(job.applicationUrl)}" 
                           class="job-apply-button" 
                           target="_blank"
                           rel="noopener noreferrer">
                            Apply Now
                        </a>
                    </div>
                </div>`;

      return jobHtml;
    }

    formatDate(dateString) {
      const date = new Date(dateString);
      return date.toLocaleDateString("en-US", {
        year: "numeric",
        month: "long",
        day: "numeric",
      });
    }

    showError(message) {
      this.loading.hide();
      this.jobsList.html(`
                <div class="error-message">
                    ${this.escapeHtml(message)}
                </div>
            `);
    }

    showMessage(message) {
      this.jobsList.html(`
                <div class="info-message">
                    ${this.escapeHtml(message)}
                </div>
            `);
    }

    escapeHtml(unsafe) {
      if (!unsafe) return "";
      return unsafe
        .toString()
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
