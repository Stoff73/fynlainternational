/**
 * Polling Utility
 * Provides a reusable polling mechanism for long-running async operations
 * Used primarily for Monte Carlo simulation result retrieval
 */

/**
 * Poll a function until it returns success or reaches max attempts
 *
 * @param {Function} fetchFunction - Async function to poll (should return Promise)
 * @param {Object} options - Polling configuration
 * @param {Number} options.interval - Interval between polls in milliseconds (default: 2000)
 * @param {Number} options.maxAttempts - Maximum number of polling attempts (default: 60)
 * @param {Function} options.shouldContinue - Function to determine if polling should continue (receives response data)
 * @param {Function} options.onProgress - Callback for each poll attempt (receives attempt number and response)
 * @returns {Promise} Resolves with final result or rejects on timeout/error
 *
 * @example
 * const result = await poll(
 *   () => investmentService.getMonteCarloResults(jobId),
 *   {
 *     interval: 3000,
 *     maxAttempts: 40,
 *     shouldContinue: (data) => data.data.status === 'running',
 *   }
 * );
 */
export async function poll(fetchFunction, options = {}) {
    const {
        interval = 2000,      // Poll every 2 seconds by default
        maxAttempts = 60,     // Max 60 attempts (2 minutes total)
        shouldContinue = null,
        onProgress = null
    } = options;

    let attempt = 0;

    while (attempt < maxAttempts) {
        attempt++;

        try {
            const response = await fetchFunction();

            // Call progress callback if provided
            if (onProgress && typeof onProgress === 'function') {
                onProgress(attempt, response);
            }

            // Check if we should continue polling
            if (shouldContinue && typeof shouldContinue === 'function') {
                if (!shouldContinue(response)) {
                    // Polling should stop - return the result
                    return response;
                }
            } else {
                // No shouldContinue function - return first successful response
                return response;
            }

            // Wait before next poll
            if (attempt < maxAttempts) {
                await sleep(interval);
            }
        } catch (error) {
            // Check multiple possible locations for status code
            const statusCode = error.response?.status || error.status || error.statusCode;
            const is404 = statusCode === 404;

            // If it's a 404, job might not be ready yet - continue polling
            if (is404 && attempt < maxAttempts) {
                await sleep(interval);
                continue;
            }

            // Other errors - reject immediately
            console.error(`Poll attempt ${attempt}: Fatal error, stopping poll`, error);
            throw new Error(`Polling failed: ${error.message}`);
        }
    }

    // Max attempts reached
    throw new Error(`Polling timeout: Maximum attempts (${maxAttempts}) reached`);
}

/**
 * Poll for Monte Carlo simulation results
 * Specialized wrapper for Monte Carlo job polling
 *
 * @param {Function} fetchFunction - Function to fetch Monte Carlo results
 * @param {Object} options - Polling options
 * @returns {Promise} Resolves when job is completed
 *
 * @example
 * const results = await pollMonteCarloJob(
 *   () => investmentService.getMonteCarloResults(jobId),
 *   {
 *     onProgress: (attempt, response) => {
 *       commit('SET_MONTE_CARLO_STATUS', response.data.status);
 *     }
 *   }
 * );
 */
export async function pollMonteCarloJob(fetchFunction, options = {}) {
    // Add initial delay to allow job to start processing
    await sleep(500);

    return poll(fetchFunction, {
        interval: 2000,        // Poll every 2 seconds
        maxAttempts: 100,      // Max ~3 minutes (2s * 100 = 200s)
        shouldContinue: (response) => {
            // Continue polling while status is 'running' or 'queued'
            const status = response?.data?.data?.status;
            return status === 'running' || status === 'queued';
        },
        ...options
    });
}

/**
 * Sleep utility
 * @param {Number} ms - Milliseconds to sleep
 * @returns {Promise} Resolves after specified time
 */
function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

export default {
    poll,
    pollMonteCarloJob
};
