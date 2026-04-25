function togglePw(id, el) {
     const input = document.getElementById(id);
     const isText = input.type === 'text';
     input.type = isText ? 'password' : 'text';
     el.style.opacity = isText ? '.4' : '.8';
}
 
function checkStrength(val) {
     const segs = [
          document.getElementById('s1'), 
          document.getElementById('s2'),
          document.getElementById('s3'), 
          document.getElementById('s4')
     ];
     let score = 0;
     if (val.length >= 8)  score++;
     if (/[A-Z]/.test(val)) score++;
     if (/[0-9]/.test(val)) score++;
     if (/[^A-Za-z0-9]/.test(val)) score++;
 
     const colors = ['#ff4d6d','#ff9a3c','#f0cc5e','#36d399'];
     segs.forEach((s, i) => {
        s.style.background = i < score ? colors[score - 1] : 'rgba(255,255,255,.1)';
     });
}
 
function validate() {
     let ok = true;
 
     const nama = document.getElementById('nama');
     const hNama = document.getElementById('hintNama');
     if (!nama.value.trim()) {
        nama.className = 'error'; 
        hNama.className = 'hint err show'; 
        ok = false;
     } 
     else {
        nama.className = 'valid'; 
        hNama.className = 'hint'; 
     }
 
     const user = document.getElementById('username');
     const hUser = document.getElementById('hintUsername');
     if (user.value.trim().length < 4) {
        user.className = 'error'; 
        hUser.className = 'hint err show'; 
        ok = false;
     } 
     else { 
        user.className = 'valid';
        hUser.className = 'hint'; }
 
     const email = document.getElementById('email');
     const hEmail = document.getElementById('hintEmail');
     const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
     if (!emailRe.test(email.value)) {
        email.className = 'error'; 
        hEmail.className = 'hint err show'; 
        ok = false;
     } 
     else { 
        email.className = 'valid'; 
        hEmail.className = 'hint'; 
     }
 
     const pw = document.getElementById('password');
     const hPw = document.getElementById('hintPw');
     if (pw.value.length < 8) {
        pw.className = 'error'; 
        hPw.className = 'hint err show'; 
        ok = false;
     } 
     else { 
        pw.className = 'valid'; 
        hPw.className = 'hint'; 
     }
 
     const kf = document.getElementById('konfirmasi');
     const hKf = document.getElementById('hintKonfirmasi');
     if (kf.value !== pw.value || !kf.value) {
        kf.className = 'error'; 
        hKf.className = 'hint err show'; 
        ok = false;
     } 
     else { 
        kf.className = 'valid';
        hKf.className = 'hint'; 
     }
 
      return ok;
}
 
function submitForm() {
     if (validate()) {
        document.getElementById('successOverlay').classList.add('show');
     }
}