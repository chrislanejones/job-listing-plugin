document.addEventListener("DOMContentLoaded", function () {
  const refreshButton = document.getElementById("refresh-jobs-button");
  const statusElement = document.getElementById("refresh-status");

  if (!refreshButton) return;

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
});
