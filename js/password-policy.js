(function () {
  // Live checklist only — register submit validation lives in register-form.js
  function bindPasswordChecklist() {
    var input = document.getElementById('password');
    var list = document.getElementById('password-rules');
    if (!input || !list) return;

    function setState(el, ok) {
      if (!el) return;
      el.classList.toggle('ok', !!ok);
      el.classList.toggle('bad', !ok);
    }

    function evaluate() {
      var value = input.value || '';
      setState(list.querySelector('[data-rule="length"]'), value.length >= 8);
      setState(list.querySelector('[data-rule="upper"]'), /[A-Z]/.test(value));
      setState(list.querySelector('[data-rule="lower"]'), /[a-z]/.test(value));
      setState(list.querySelector('[data-rule="number"]'), /\d/.test(value));
      setState(list.querySelector('[data-rule="special"]'), /[@$!%*?&#^()_\-+=\[\]{}:;,.<>\/\\|~`"']/.test(value));
      setState(list.querySelector('[data-rule="space"]'), value.length > 0 && !/\s/.test(value));
    }

    input.addEventListener('input', evaluate);
    input.addEventListener('focus', evaluate);
    evaluate();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindPasswordChecklist);
  } else {
    bindPasswordChecklist();
  }
})();
