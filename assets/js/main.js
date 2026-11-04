/**
 * Campus LostLink — Interactive Vanilla JavaScript Module
 */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Password Visibility Toggle
  initPasswordToggles();

  // 2. Image Upload Preview
  initImagePreviews();

  // 3. Modals (e.g. Submit Claim Request)
  initModals();

  // 4. Mobile Navigation & Sidebar
  initMobileNav();

  // 5. Delete Confirmations
  initConfirmations();

  // 6. Live Filter / Debounce Search
  initLiveSearch();
});

/**
 * Password Visibility Toggle
 */
function initPasswordToggles() {
  const toggleButtons = document.querySelectorAll('.password-toggle-btn');
  toggleButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const targetId = btn.getAttribute('data-target');
      const input = targetId ? document.getElementById(targetId) : btn.previousElementSibling;
      if (!input) return;

      const icon = btn.querySelector('i');
      if (input.type === 'password') {
        input.type = 'text';
        if (icon) {
          icon.classList.remove('fa-eye');
          icon.classList.add('fa-eye-slash');
        }
      } else {
        input.type = 'password';
        if (icon) {
          icon.classList.remove('fa-eye-slash');
          icon.classList.add('fa-eye');
        }
      }
    });
  });
}

/**
 * Image Upload Preview
 */
function initImagePreviews() {
  const fileInputs = document.querySelectorAll('input[type="file"][data-preview]');
  fileInputs.forEach(input => {
    const previewContainerId = input.getAttribute('data-preview');
    const previewContainer = document.getElementById(previewContainerId);
    if (!previewContainer) return;

    input.addEventListener('change', () => {
      const file = input.files && input.files[0];
      if (file) {
        if (file.size > 2 * 1024 * 1024) {
          alert('Image size exceeds 2MB limit. Please select a smaller file.');
          input.value = '';
          previewContainer.style.display = 'none';
          return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
          let img = previewContainer.querySelector('img');
          if (!img) {
            img = document.createElement('img');
            previewContainer.appendChild(img);
          }
          img.src = e.target.result;
          previewContainer.style.display = 'block';
        };
        reader.readAsDataURL(file);
      } else {
        previewContainer.style.display = 'none';
      }
    });
  });
}

/**
 * Modal dialog handling
 */
function initModals() {
  // Open modal buttons
  const openButtons = document.querySelectorAll('[data-modal-open]');
  openButtons.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const modalId = btn.getAttribute('data-modal-open');
      openModal(modalId);
    });
  });

  // Close modal buttons
  const closeButtons = document.querySelectorAll('[data-modal-close]');
  closeButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const modal = btn.closest('.modal-overlay');
      if (modal) closeModal(modal);
    });
  });

  // Close on backdrop click
  const modals = document.querySelectorAll('.modal-overlay');
  modals.forEach(modal => {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        closeModal(modal);
      }
    });
  });

  // Close on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const openModalElem = document.querySelector('.modal-overlay.open');
      if (openModalElem) closeModal(openModalElem);
    }
  });
}

function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
    const firstInput = modal.querySelector('input:not([type="hidden"]), textarea');
    if (firstInput) firstInput.focus();
  }
}

function closeModal(modal) {
  modal.classList.remove('open');
  document.body.style.overflow = '';
}

/**
 * Mobile Navigation Toggle
 */
function initMobileNav() {
  const toggleBtn = document.querySelector('.mobile-toggle-btn');
  const navLinks = document.querySelector('.nav-links');

  if (toggleBtn && navLinks) {
    toggleBtn.addEventListener('click', () => {
      navLinks.classList.toggle('mobile-open');
    });
  }
}

/**
 * Confirmations on sensitive actions (Delete, etc.)
 */
function initConfirmations() {
  const confirmElements = document.querySelectorAll('[data-confirm]');
  confirmElements.forEach(el => {
    el.addEventListener('click', (e) => {
      const message = el.getAttribute('data-confirm') || 'Are you sure you want to proceed?';
      if (!confirm(message)) {
        e.preventDefault();
      }
    });
  });
}

/**
 * Live search and filter debouncing on items page
 */
function initLiveSearch() {
  const searchInput = document.getElementById('search-item-input');
  const filterForm = document.getElementById('filter-items-form');

  if (!searchInput || !filterForm) return;

  let debounceTimer = null;

  searchInput.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      filterForm.submit();
    }, 600);
  });
}
