// Question Bank - Minimal JavaScript for Enhanced UX

document.addEventListener('DOMContentLoaded', function() {
    
    // Auto-hide success/error messages after 5 seconds
    const messages = document.querySelectorAll('.success-message, .error-message');
    messages.forEach(function(message) {
        setTimeout(function() {
            message.style.opacity = '0';
            message.style.transition = 'opacity 0.3s ease';
            setTimeout(function() {
                message.style.display = 'none';
            }, 300);
        }, 5000);
    });
    
    // Enhanced form validation feedback
    const forms = document.querySelectorAll('form');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(function(field) {
                if (!field.value.trim()) {
                    field.style.borderColor = 'var(--error-red)';
                    isValid = false;
                } else {
                    field.style.borderColor = 'var(--divider-grey)';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                // Focus on first invalid field
                const firstInvalid = form.querySelector('[required]:invalid, [required][style*="border-color: var(--error-red)"]');
                if (firstInvalid) {
                    firstInvalid.focus();
                }
            }
        });
    });
    
    // Reset field border color on focus
    const inputs = document.querySelectorAll('input, textarea, select');
    inputs.forEach(function(input) {
        input.addEventListener('focus', function() {
            this.style.borderColor = 'var(--soft-grey)';
        });
        
        input.addEventListener('blur', function() {
            if (this.hasAttribute('required') && !this.value.trim()) {
                this.style.borderColor = 'var(--divider-grey)';
            }
        });
    });
    
    // Select all checkboxes functionality for exam generation
    const selectAllBtn = document.getElementById('select-all-questions');
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('input[name="selected_questions[]"]');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = !allChecked;
            });
            
            this.textContent = allChecked ? 'Select All' : 'Deselect All';
        });
    }
    
    // Character counter for question text
    const questionTextarea = document.getElementById('question_text');
    if (questionTextarea) {
        const counter = document.createElement('div');
        counter.style.cssText = 'font-size: 0.875rem; color: var(--text-grey); text-align: right; margin-top: 4px;';
        questionTextarea.parentNode.appendChild(counter);
        
        function updateCounter() {
            const count = questionTextarea.value.length;
            counter.textContent = count + ' characters';
            
            if (count > 1000) {
                counter.style.color = 'var(--error-red)';
            } else {
                counter.style.color = 'var(--text-grey)';
            }
        }
        
        questionTextarea.addEventListener('input', updateCounter);
        updateCounter(); // Initial count
    }
    
    // Smooth transitions for interactive elements
    const interactiveElements = document.querySelectorAll('button, .btn, a, input, textarea, select');
    interactiveElements.forEach(function(element) {
        if (!element.style.transition) {
            element.style.transition = 'opacity 0.2s ease, border-color 0.2s ease';
        }
    });
    
});

// Utility function for showing temporary notifications
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = type === 'error' ? 'error-message' : 'success-message';
    notification.textContent = message;
    notification.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 1000; max-width: 300px;';
    
    document.body.appendChild(notification);
    
    setTimeout(function() {
        notification.style.opacity = '0';
        setTimeout(function() {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// Export for use in other scripts if needed
window.QuestionBank = {
    showNotification: showNotification
};
