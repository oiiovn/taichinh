/**
 * Định dạng ô nhập số tiền VND: hiển thị xx.xxx.xxx khi gõ, submit gửi số thô (không dấu chấm).
 * Gắn data-format-vnd cho input số tiền; data-format-vnd-allow-negative="1" nếu cho phép âm.
 */
function stripToRaw(str, allowNegative = false) {
  if (str == null || str === '') return '';
  const s = String(str).replace(/\s/g, '');
  let sign = '';
  let rest = s;
  if (allowNegative && s.startsWith('-')) {
    sign = '-';
    rest = s.slice(1);
  }
  const digits = rest.replace(/\D/g, '');
  return sign + digits;
}

function formatVndDisplay(raw, allowNegative = false) {
  const s = stripToRaw(raw, allowNegative);
  if (s === '' || s === '-') return s;
  const [sign, num] = s.startsWith('-') ? ['-', s.slice(1)] : ['', s];
  if (num === '') return sign;
  const formatted = num.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  return sign + formatted;
}

export function initFormatVnd() {
  document.querySelectorAll('[data-format-vnd]').forEach((input) => {
    const allowNegative = input.getAttribute('data-format-vnd-allow-negative') === '1' || input.getAttribute('data-format-vnd-allow-negative') === 'true';
    if (input.type === 'number') {
      input.type = 'text';
      input.inputMode = 'numeric';
    }
    const format = () => {
      const start = input.value;
      const raw = stripToRaw(start, allowNegative);
      const displayed = formatVndDisplay(raw, allowNegative);
      if (displayed !== start) {
        const pos = input.selectionEnd ?? displayed.length;
        input.value = displayed;
        input.setSelectionRange(displayed.length, displayed.length);
      }
    };
    input.addEventListener('input', format);
    input.addEventListener('paste', (e) => {
      e.preventDefault();
      const pasted = (e.clipboardData || window.clipboardData).getData('text');
      const raw = stripToRaw(pasted, allowNegative);
      const displayed = formatVndDisplay(raw, allowNegative);
      input.value = displayed;
    });
    format();
  });

  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', () => {
      form.querySelectorAll('[data-format-vnd]').forEach((input) => {
        const allowNegative = input.getAttribute('data-format-vnd-allow-negative') === '1' || input.getAttribute('data-format-vnd-allow-negative') === 'true';
        const raw = stripToRaw(input.value, allowNegative);
        input.value = raw;
      });
    });
  });
}
