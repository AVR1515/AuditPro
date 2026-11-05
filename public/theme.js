'use strict';
const picker = document.getElementById('theme');
try { picker.value = localStorage.getItem('auditpro-theme') ?? ''; } catch {}
function applyTheme() {
  if (picker.value) document.documentElement.dataset.theme = picker.value;
  else delete document.documentElement.dataset.theme;
}
applyTheme();
picker.addEventListener('change', () => {
  applyTheme();
  try { localStorage.setItem('auditpro-theme', picker.value); } catch {}
});
document.getElementById('print-report')?.addEventListener('click', () => window.print());
