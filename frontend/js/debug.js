// Quick nav-state inspector — paste in browser console
const user = JSON.parse(localStorage.getItem('user') || 'null');
const token = localStorage.getItem('token');
console.log('=== Auth state debug ===');
console.log('user  :', user);
console.log('token :', token ? token.substring(0, 40) + '...' : 'MISSING');
document.querySelectorAll('.auth-only').forEach(el => {
  console.log('auth-only  :', el.textContent.trim(), '| display:', el.style.display);
});
document.querySelectorAll('.user-only').forEach(el => {
  console.log('user-only  :', el.textContent.trim(), '| display:', el.style.display);
});
console.log('override styles?:', getComputedStyle(document.querySelector('.auth-only')).display);
console.log('override user-?:', getComputedStyle(document.querySelector('.user-only')).display);
