class OfflineHandler {
    constructor() {
        this.storageKey = 'pixies_stock_entry_draft';
        this.autoSaveInterval = 5000; // 5 seconds
        this.isOnline = navigator.onLine;
        this.formSelector = 'form[data-stock-form]';
        this.retryButtonSelector = '[data-retry-submit]';
        
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.restoreFormData();
        this.startAutoSave();
        this.updateOnlineStatus();
    }

    setupEventListeners() {
        // Online/offline events
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.updateOnlineStatus();
            this.showNotification('Connection restored! 🌐', 'success');
        });

        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.updateOnlineStatus();
            this.showNotification('No internet connection - data saved locally 💾', 'warning');
        });

        // Form input events
        document.addEventListener('input', (e) => {
            if (e.target.closest(this.formSelector)) {
                this.saveFormData();
            }
        });

        // Retry button click
        document.addEventListener('click', (e) => {
            if (e.target.closest(this.retryButtonSelector)) {
                this.retrySubmission();
            }
        });

        // Page visibility change (app switching)
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.saveFormData();
            }
        });
    }

    saveFormData() {
        const form = document.querySelector(this.formSelector);
        if (!form) return;

        try {
            const formData = new FormData(form);
            const data = {};
            
            // Convert FormData to plain object
            for (let [key, value] of formData.entries()) {
                // Handle arrays (like stock_items, expenses, payments)
                if (key.includes('[')) {
                    const [base, index, field] = key.match(/([^\[]+)\[(\d+)\]\[([^\]]+)\]/) || [];
                    if (base && index !== undefined && field) {
                        if (!data[base]) data[base] = [];
                        if (!data[base][index]) data[base][index] = {};
                        data[base][index][field] = value;
                    }
                } else {
                    data[key] = value;
                }
            }

            // Add metadata
            data._timestamp = Date.now();
            data._url = window.location.href;

            localStorage.setItem(this.storageKey, JSON.stringify(data));
            console.log('Form data saved locally');
        } catch (error) {
            console.error('Error saving form data:', error);
        }
    }

    restoreFormData() {
        try {
            const savedData = localStorage.getItem(this.storageKey);
            if (!savedData) return;

            const data = JSON.parse(savedData);
            const form = document.querySelector(this.formSelector);
            if (!form) return;

            // Check if we're on the same page
            if (data._url && data._url !== window.location.href) {
                // Different page, clear old data
                localStorage.removeItem(this.storageKey);
                return;
            }

            // Restore form fields
            Object.keys(data).forEach(key => {
                if (key.startsWith('_')) return; // Skip metadata

                const value = data[key];
                const input = form.querySelector(`[name="${key}"]`);
                
                if (input) {
                    if (input.type === 'checkbox') {
                        input.checked = value;
                    } else if (input.type === 'radio') {
                        const radioInput = form.querySelector(`[name="${key}"][value="${value}"]`);
                        if (radioInput) radioInput.checked = true;
                    } else {
                        input.value = value;
                    }
                    
                    // Trigger change event for Livewire/Alpine reactivity
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            // Restore array fields (stock_items, expenses, etc.)
            Object.keys(data).forEach(key => {
                if (Array.isArray(value)) {
                    value.forEach((item, index) => {
                        Object.keys(item).forEach(field => {
                            const inputName = `${key}[${index}][${field}]`;
                            const input = form.querySelector(`[name="${inputName}"]`);
                            if (input) {
                                input.value = item[field];
                                input.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        });
                    });
                }
            });

            console.log('Form data restored from local storage');
            this.showNotification('Previous data restored 💾', 'info');
        } catch (error) {
            console.error('Error restoring form data:', error);
        }
    }

    clearSavedData() {
        localStorage.removeItem(this.storageKey);
    }

    startAutoSave() {
        setInterval(() => {
            this.saveFormData();
        }, this.autoSaveInterval);
    }

    updateOnlineStatus() {
        const statusIndicator = document.querySelector('[data-online-status]');
        if (statusIndicator) {
            statusIndicator.textContent = this.isOnline ? '🌐 Online' : '💾 Offline';
            statusIndicator.className = this.isOnline ? 'text-green-600' : 'text-orange-600';
        }

        // Show/hide retry button
        const retryButton = document.querySelector(this.retryButtonSelector);
        if (retryButton) {
            retryButton.style.display = this.isOnline ? 'none' : 'inline-block';
        }
    }

    async retrySubmission() {
        const form = document.querySelector(this.formSelector);
        if (!form || this.isOnline) return;

        this.showNotification('Attempting to submit... 🔄', 'info');

        try {
            // Check if we're online
            if (!navigator.onLine) {
                throw new Error('Still offline');
            }

            // Submit the form
            const formData = new FormData(form);
            const submitButton = form.querySelector('button[type="submit"]');
            
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Submitting...';
            }

            const response = await fetch(form.action, {
                method: form.method,
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html',
                }
            });

            if (response.ok) {
                this.clearSavedData();
                this.showNotification('Submission successful! ✅', 'success');
                
                // Redirect or update page
                const redirectUrl = response.headers.get('X-Redirect') || form.getAttribute('data-redirect');
                if (redirectUrl) {
                    window.location.href = redirectUrl;
                } else {
                    // Reload page to show updated content
                    window.location.reload();
                }
            } else {
                throw new Error(`Server error: ${response.status}`);
            }
        } catch (error) {
            console.error('Retry submission failed:', error);
            this.showNotification('Submission failed - will retry when online 💾', 'error');
            
            // Re-enable submit button
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = 'Submit';
            }
        }
    }

    showNotification(message, type = 'info') {
        // Remove existing notifications
        const existingNotification = document.querySelector('[data-notification]');
        if (existingNotification) {
            existingNotification.remove();
        }

        // Create notification element
        const notification = document.createElement('div');
        notification.setAttribute('data-notification', '');
        notification.className = `fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg max-w-sm ${
            type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
            type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
            type === 'warning' ? 'bg-orange-100 text-orange-800 border border-orange-200' :
            'bg-blue-100 text-blue-800 border border-blue-200'
        }`;
        
        notification.innerHTML = `
            <div class="flex items-center">
                <span class="text-sm font-medium">${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-3 text-lg leading-none">&times;</button>
            </div>
        `;

        document.body.appendChild(notification);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }

    // Method to check if there's unsaved data
    hasUnsavedData() {
        return localStorage.getItem(this.storageKey) !== null;
    }

    // Method to get saved data info
    getSavedDataInfo() {
        try {
            const savedData = localStorage.getItem(this.storageKey);
            if (!savedData) return null;

            const data = JSON.parse(savedData);
            return {
                timestamp: data._timestamp,
                url: data._url,
                age: Date.now() - data._timestamp
            };
        } catch (error) {
            return null;
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.offlineHandler = new OfflineHandler();
});

// Also initialize immediately in case DOM is already loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.offlineHandler = new OfflineHandler();
    });
} else {
    window.offlineHandler = new OfflineHandler();
}
