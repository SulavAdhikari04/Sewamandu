(function () {
  function capitalizeWords(value) {
    return value.replace(/[^\s]+/g, function (word) {
      return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
    });
  }

  function normalizeInput(input, trimValue) {
    var value = input.value;
    if (trimValue) {
      value = value.replace(/\s+/g, ' ').trim();
    }
    input.value = capitalizeWords(value);
  }

  function bindInput(input) {
    input.setAttribute('autocapitalize', 'words');

    input.addEventListener('blur', function () {
      normalizeInput(input, false);
    });

    var form = input.form;
    if (form && !form.dataset.capitalizeBound) {
      form.dataset.capitalizeBound = '1';
      form.addEventListener('submit', function () {
        form.querySelectorAll('[data-capitalize="words"]').forEach(function (field) {
          normalizeInput(field, true);
        });
      });
    }
  }

  function init() {
    document.querySelectorAll('[data-capitalize="words"]').forEach(bindInput);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
