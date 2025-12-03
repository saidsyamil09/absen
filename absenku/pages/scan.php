<?php
// Halaman scan QR/barcode (menggunakan html5-qrcode)
$user = current_user();
?>
<div class="container-fluid">
  <h4>Scan Absensi (QR/Barcode)</h4>
  <div class="card mb-3">
    <div class="card-body">
      <p>Pastikan kamera diizinkan. QR / Barcode harus berisi NIS siswa atau ID (mis: 2512147).</p>
      <div id="qr-reader" style="width:500px"></div>
      <div id="qr-result" class="mt-3"></div>
    </div>
  </div>
</div>

<!-- html5-qrcode CDN -->
<script src="https://unpkg.com/html5-qrcode@2.3.7/minified/html5-qrcode.min.js"></script>
<script>
  function onScanSuccess(decodedText, decodedResult) {
    // kirim ke server
    document.getElementById('qr-result').innerHTML = 'Memproses: ' + decodedText;
    fetch('scan_attendance.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({code: decodedText})
    }).then(r=>r.json()).then(data=>{
      if(data.success){
        document.getElementById('qr-result').innerHTML = '<div class="alert alert-success">'+data.message+'</div>';
      } else {
        document.getElementById('qr-result').innerHTML = '<div class="alert alert-danger">'+data.message+'</div>';
      }
      // restart scanning after 2.5s
      setTimeout(()=>{ html5QrcodeScanner.clear().then(()=> startScanner()); }, 2500);
    }).catch(err=>{
      document.getElementById('qr-result').innerHTML = '<div class="alert alert-danger">Error komunikasi</div>';
    });
    // stop after success handled (handled above)
  }

  function onScanError(errorMessage) {
    // handle scan error if needed
    // console.warn(errorMessage);
  }

  let html5QrcodeScanner;
  function startScanner(){
    html5QrcodeScanner = new Html5Qrcode("qr-reader");
    const config = { fps: 10, qrbox: 250 };
    html5QrcodeScanner.start({ facingMode: "environment" }, config, onScanSuccess, onScanError).catch(err=>{
      document.getElementById('qr-result').innerHTML = '<div class="alert alert-warning">Tidak dapat mengaktifkan kamera: '+err+'</div>';
    });
  }

  startScanner();
</script>