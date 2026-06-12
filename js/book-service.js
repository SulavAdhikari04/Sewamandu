(function () {
  const form = document.getElementById('booking-form');
  if (!form) {
    return;
  }

  const dateInput = document.getElementById('booking-date');
  const customDateInput = document.getElementById('custom-date');
  const customDatePanel = form.querySelector('.custom-date-panel');
  const dateSlots = form.querySelectorAll('.date-slot');
  const timeInput = document.getElementById('booking-time');
  const timeSlots = form.querySelectorAll('.time-slot');
  const scheduleHint = form.querySelector('.schedule-hint');

  function updateScheduleHint() {
    if (!scheduleHint || !dateInput || !timeInput) {
      return;
    }

    if (dateInput.value && timeInput.value) {
      scheduleHint.textContent = 'Selected: ' + dateInput.value + ' at ' + timeInput.value;
      scheduleHint.hidden = false;
      return;
    }

    scheduleHint.hidden = true;
  }

  function selectDate(value, source) {
    if (!dateInput || !value) {
      return;
    }

    dateInput.value = value;

    dateSlots.forEach(function (slot) {
      const isSelected = slot.dataset.date === value && source !== 'custom';
      slot.classList.toggle('is-selected', isSelected);
      slot.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
    });

    if (customDateInput) {
      if (source === 'custom') {
        customDateInput.value = value;
      } else {
        customDateInput.value = '';
      }
    }

    if (customDatePanel) {
      customDatePanel.classList.toggle('is-active', source === 'custom');
    }

    updateScheduleHint();
  }

  function selectTime(value) {
    if (!timeInput || !value) {
      return;
    }

    timeInput.value = value;

    timeSlots.forEach(function (slot) {
      const isSelected = slot.dataset.time === value;
      slot.classList.toggle('is-selected', isSelected);
      slot.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
    });

    updateScheduleHint();
  }

  dateSlots.forEach(function (slot) {
    slot.addEventListener('click', function () {
      selectDate(slot.dataset.date, 'slot');
    });
  });

  if (customDateInput) {
    customDateInput.addEventListener('change', function () {
      if (customDateInput.value) {
        selectDate(customDateInput.value, 'custom');
      }
    });
  }

  timeSlots.forEach(function (slot) {
    slot.addEventListener('click', function () {
      selectTime(slot.dataset.time);
    });
  });

  form.addEventListener('submit', function (event) {
    if (!dateInput.value) {
      event.preventDefault();
      window.alert('Please select a date.');
      return;
    }

    if (!timeInput.value) {
      event.preventDefault();
      window.alert('Please select a time.');
    }
  });

  if (dateInput.value) {
    const matchingSlot = Array.prototype.find.call(dateSlots, function (slot) {
      return slot.dataset.date === dateInput.value;
    });

    if (matchingSlot) {
      selectDate(dateInput.value, 'slot');
    } else if (customDateInput) {
      customDateInput.value = dateInput.value;
      selectDate(dateInput.value, 'custom');
    }
  }

  if (timeInput && timeInput.value) {
    selectTime(timeInput.value);
  }
})();
