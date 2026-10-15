// Peningkatan antarmuka (JavaScript biasa, tanpa pustaka, tanpa JavaScript inline karena CSP).
(function () {
  'use strict';

  // 1. Konfirmasi form: <form data-konfirmasi="Pesan">
  document.addEventListener('submit', function (e) {
    var form = e.target;
    var pesan = form.getAttribute && form.getAttribute('data-konfirmasi');
    if (pesan && !window.confirm(pesan)) {
      e.preventDefault();
      return;
    }
    // 2. Cegah kirim ganda: tombol submit dinonaktifkan sebentar setelah dikirim.
    var pengirim = e.submitter || form.querySelector('[type=submit]');
    if (pengirim && !form.hasAttribute('data-bebas-kunci')) {
      window.setTimeout(function () {
        pengirim.classList.add('memproses');
        pengirim.setAttribute('aria-busy', 'true');
        pengirim.disabled = true;
      }, 0);
    }
  });

  // Tombol "kembali" dari cache peramban: pulihkan tombol yang terkunci.
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) {
      document.querySelectorAll('.memproses').forEach(function (t) {
        t.classList.remove('memproses');
        t.removeAttribute('aria-busy');
        t.disabled = false;
      });
    }
  });

  // 3. Tabel responsif: salin teks <th> ke atribut data-label pada setiap <td>.
  document.querySelectorAll('table.tabel').forEach(function (tabel) {
    var judul = Array.prototype.map.call(tabel.querySelectorAll('thead th'), function (th) {
      return th.textContent.trim();
    });
    if (!judul.length) { return; }
    tabel.classList.add('responsif');
    tabel.querySelectorAll('tbody tr').forEach(function (tr) {
      Array.prototype.forEach.call(tr.children, function (td, i) {
        if (td.tagName === 'TD' && !td.hasAttribute('data-label') && !td.hasAttribute('colspan')) {
          td.setAttribute('data-label', judul[i] || '');
        }
      });
    });
  });

  // 4. Menu portal (layar kecil).
  var bilah = document.querySelector('.bilah-atas');
  var pemicu = document.querySelector('.pemicu-menu');
  if (bilah && pemicu) {
    pemicu.addEventListener('click', function () {
      var buka = bilah.classList.toggle('terbuka');
      pemicu.setAttribute('aria-expanded', buka ? 'true' : 'false');
    });
  }

  // 5. Sidebar petugas (layar kecil): tombol, tirai, dan tombol Esc.
  var tubuh = document.body;
  function setSidebar(buka) {
    tubuh.classList.toggle('sidebar-terbuka', buka);
    var t = document.querySelector('.pemicu-sidebar');
    if (t) { t.setAttribute('aria-expanded', buka ? 'true' : 'false'); }
  }
  document.querySelectorAll('.pemicu-sidebar, .tirai').forEach(function (el) {
    el.addEventListener('click', function () {
      setSidebar(!tubuh.classList.contains('sidebar-terbuka'));
    });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      setSidebar(false);
      if (bilah) { bilah.classList.remove('terbuka'); }
    }
  });

  // 6. Pesan umpan balik dapat ditutup.
  document.querySelectorAll('.tutup').forEach(function (b) {
    b.addEventListener('click', function () {
      var induk = b.closest('.sukses, .galat');
      if (induk) { induk.remove(); }
    });
  });
  // 7. Menu sidebar petugas: submenu dapat dibuka/ditutup (akordeon).
  document.querySelectorAll('.menu-pemicu').forEach(function (tombol) {
    tombol.addEventListener('click', function () {
      var grup = tombol.closest('.menu-grup');
      var buka = !grup.classList.contains('terbuka');
      grup.classList.toggle('terbuka', buka);
      tombol.setAttribute('aria-expanded', buka ? 'true' : 'false');
    });
  });

  // 8. Menu pengguna pada header: buka/tutup, tutup saat klik di luar atau tekan Esc.
  var tombolPengguna = document.querySelector('.siap-pengguna-tombol');
  var menuPengguna = document.getElementById('menu-pengguna');
  if (tombolPengguna && menuPengguna) {
    var tutupMenu = function () {
      menuPengguna.hidden = true;
      tombolPengguna.setAttribute('aria-expanded', 'false');
    };
    tombolPengguna.addEventListener('click', function (e) {
      e.stopPropagation();
      var tampil = menuPengguna.hidden;
      menuPengguna.hidden = !tampil;
      tombolPengguna.setAttribute('aria-expanded', tampil ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
      if (!menuPengguna.hidden && !menuPengguna.contains(e.target)) { tutupMenu(); }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { tutupMenu(); }
    });
  }
  // 9. Modul/submodul: layar kecil = akordeon (kelompok aktif terbuka); layar lebar = panel samping.
  var layarKecil = window.matchMedia('(max-width: 959px)');
  document.querySelectorAll('.menu-grup').forEach(function (g) {
    g.classList.toggle('terbuka', layarKecil.matches && g.classList.contains('aktif-grup'));
    var p = g.querySelector('.menu-pemicu');
    if (p) { p.setAttribute('aria-expanded', g.classList.contains('terbuka') ? 'true' : 'false'); }
  });
  document.addEventListener('click', function (e) {
    if (layarKecil.matches || e.target.closest('.menu-grup')) { return; }
    document.querySelectorAll('.menu-grup.terbuka').forEach(function (g) {
      g.classList.remove('terbuka');
      var p = g.querySelector('.menu-pemicu');
      if (p) { p.setAttribute('aria-expanded', 'false'); }
    });
  });
})();
