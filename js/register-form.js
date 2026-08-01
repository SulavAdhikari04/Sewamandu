(function () {
  var HIGHLIGHT_MS = 2200;

  function isPasswordStrong(value) {
    if (!value || value.length < 8) return false;
    if (/\s/.test(value)) return false;
    if (!/[A-Z]/.test(value)) return false;
    if (!/[a-z]/.test(value)) return false;
    if (!/\d/.test(value)) return false;
    if (!/[@$!%*?&#^()_\-+=\[\]{}:;,.<>\/\\|~`"']/.test(value)) return false;
    return true;
  }

  function clearFieldError(el) {
    if (!el) return;
    el.classList.remove('is-invalid', 'field-flash');
    var field = el.closest('.auth-field, .auth-file');
    if (!field) return;
    var tip = field.querySelector('.field-inline-error');
    if (tip) tip.remove();
  }

  function showFieldError(el, message) {
    if (!el) return;
    var field = el.closest('.auth-field, .auth-file') || el.parentElement;
    el.classList.add('is-invalid', 'field-flash');

    if (field) {
      var tip = field.querySelector('.field-inline-error');
      if (!tip) {
        tip = document.createElement('div');
        tip.className = 'field-inline-error';
        tip.setAttribute('role', 'alert');
        field.appendChild(tip);
      }
      tip.textContent = message || 'Please match format';
    }

    window.setTimeout(function () {
      el.classList.remove('field-flash');
    }, HIGHLIGHT_MS);
  }

  function validateRegisterForm(form) {
    var name = form.querySelector('#name');
    var email = form.querySelector('#email');
    var phone = form.querySelector('#phone');
    var password = form.querySelector('#password');
    var confirm = form.querySelector('#confirm-password');
    var role = form.querySelector('#role');
    var providerDoc = form.querySelector('#provider_document');
    var passwordWarn = form.querySelector('#password-format-warn');

    var fields = [name, email, phone, password, confirm, role, providerDoc];
    fields.forEach(clearFieldError);
    if (passwordWarn) passwordWarn.classList.remove('show');

    var firstInvalid = null;

    function fail(el, message) {
      showFieldError(el, message);
      if (!firstInvalid) firstInvalid = el;
    }

    if (!name || !(name.value || '').trim()) {
      fail(name, 'Please enter your full name');
    }

    var emailVal = ((email && email.value) || '').trim().toLowerCase();
    if (email) email.value = emailVal;
    if (!emailVal) {
      fail(email, 'Please enter your email');
    } else if (!/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i.test(emailVal)) {
      fail(email, 'Please match format');
    }

    var phoneVal = ((phone && phone.value) || '').trim();
    if (!phoneVal) {
      fail(phone, 'Please enter your phone number');
    } else if (!/^9[78][0-9]{8}$/.test(phoneVal)) {
      fail(phone, 'Please match format');
    }

    if (!password || !isPasswordStrong(password.value || '')) {
      fail(password, 'Please match format');
      if (passwordWarn) {
        passwordWarn.textContent = 'Please match format';
        passwordWarn.classList.add('show');
      }
    }

    if (!confirm || !(confirm.value || '')) {
      fail(confirm, 'Please confirm your password');
    } else if (password && (confirm.value || '') !== (password.value || '')) {
      fail(confirm, 'Passwords do not match');
      if (passwordWarn) {
        passwordWarn.textContent = 'Please match format';
        passwordWarn.classList.add('show');
      }
    }

    if (!role || !(role.value || '')) {
      fail(role, 'Please select your role');
    } else if (role.value === 'provider') {
      if (!providerDoc || !providerDoc.files || !providerDoc.files.length) {
        fail(providerDoc, 'Please attach a verification document');
      }
    }

    if (firstInvalid) {
      firstInvalid.focus({ preventScroll: true });
      firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return false;
    }

    return true;
  }

  function bind() {
    var form = document.getElementById('register-form');
    if (!form) return;

    form.setAttribute('novalidate', 'novalidate');

    ['name', 'email', 'phone', 'password', 'confirm-password', 'role', 'provider_document'].forEach(function (id) {
      var el = document.getElementById(id);
      if (!el) return;
      el.addEventListener('input', function () {
        clearFieldError(el);
        if (id === 'password' || id === 'confirm-password') {
          var warn = document.getElementById('password-format-warn');
          if (warn) warn.classList.remove('show');
        }
      });
      el.addEventListener('change', function () {
        clearFieldError(el);
      });
    });

    form.addEventListener(
      'submit',
      function (e) {
        if (!validateRegisterForm(form)) {
          e.preventDefault();
          e.stopPropagation();
          return false;
        }
      },
      true
    );
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind);
  } else {
    bind();
  }
})();
