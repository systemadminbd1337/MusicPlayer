<?php
session_start();
require_once "config.php";
include "header.php"; // ✅ common header include

// ডাটাবেস থেকে FAQ আনুন
$faqs = $pdo->query("SELECT * FROM k_faq WHERE visible=1 ORDER BY sort_order,id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>FAQ</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background: #0b0d21;
    background: radial-gradient(ellipse at center, #1a1f3c 0%, #0b0d21 70%);
    color:#eee;
    font-family:Arial;
    min-height: 100vh;
    position: relative;
    overflow-x: hidden;
}
body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: 
        radial-gradient(circle at 20% 80%, rgba(168, 85, 247, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(96, 165, 250, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 40% 40%, rgba(99, 102, 241, 0.05) 0%, transparent 50%);
    pointer-events: none;
    z-index: -1;
}
h1{
    background: linear-gradient(90deg, #a855f7, #60a5fa, #a855f7);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-size: 200% auto;
    font-weight: 800;
    text-shadow: 0 0 30px rgba(168, 85, 247, 0.3);
    animation: shimmer 3s ease-in-out infinite;
}
@keyframes shimmer {
    0%, 100% { background-position: 0% center; }
    50% { background-position: 200% center; }
}
.hero-text{
    font-size:18px;
    color:#a1a1b5;
    text-align:center;
    margin-bottom:25px;
    text-shadow: 0 0 10px rgba(255, 255, 255, 0.1);
}
.search-wrapper{
    display:flex;
    justify-content:center;
    margin-bottom:40px;
}
.search-box{
    background: rgba(26, 31, 60, 0.8);
    border: 1px solid rgba(168, 85, 247, 0.3);
    color: #fff;
    border-radius: 40px;
    padding: 15px 20px;
    font-size: 16px;
    width: 60%;
    max-width: 600px;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
    box-shadow: 0 0 20px rgba(168, 85, 247, 0.1);
}
.search-box:focus {
    outline: none;
    border-color: #a855f7;
    box-shadow: 0 0 30px rgba(168, 85, 247, 0.3);
    background: rgba(26, 31, 60, 0.9);
}
.search-box::placeholder {
    color: #888;
}

.faq-card {
    background: rgba(17, 21, 43, 0.8);
    border-radius: 10px;
    margin-bottom: 10px;
    border: 1px solid rgba(168, 85, 247, 0.1);
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
    box-shadow: 0 0 15px rgba(168, 85, 247, 0.05);
}
.faq-card:hover {
    border-color: rgba(168, 85, 247, 0.3);
    box-shadow: 0 0 25px rgba(168, 85, 247, 0.2);
    transform: translateY(-2px);
}
.accordion-button {
    background: rgba(17, 21, 43, 0.9);
    color: #fff;
    border: none;
    border-radius: 10px !important;
    padding: 20px;
    font-weight: 500;
    transition: all 0.3s ease;
}
.accordion-button:not(.collapsed) {
    background: rgba(29, 33, 64, 0.9);
    color: #fff;
    box-shadow: 0 0 20px rgba(168, 85, 247, 0.2);
}
.accordion-button::after {
    filter: invert(1);
    transition: all 0.3s ease;
}
.accordion-button:not(.collapsed)::after {
    filter: invert(1) brightness(2);
}
.accordion-body {
    color: #fff !important;
    font-size: 15px;
    text-align: left;
    background: rgba(29, 33, 64, 0.5);
    border-radius: 0 0 10px 10px;
    line-height: 1.6;
}
.tab-btn {
    background: rgba(29, 33, 64, 0.8);
    color: #aaa;
    border: 1px solid transparent;
    margin: 3px;
    padding: 8px 15px;
    border-radius: 20px;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}
.tab-btn:hover {
    border-color: rgba(168, 85, 247, 0.3);
    color: #fff;
    transform: translateY(-2px);
}
.tab-btn.active {
    background: linear-gradient(90deg, #a855f7, #6366f1);
    color: #fff;
    border-color: transparent;
    box-shadow: 0 0 20px rgba(168, 85, 247, 0.4);
    transform: translateY(-2px);
}
.footer-box {
    background: rgba(17, 21, 43, 0.8);
    padding: 30px;
    border-radius: 12px;
    margin-top: 30px;
    text-align: center;
    border: 1px solid rgba(168, 85, 247, 0.1);
    backdrop-filter: blur(10px);
    box-shadow: 0 0 30px rgba(168, 85, 247, 0.1);
}
.footer-box h3 {
    color: #a855f7;
    text-shadow: 0 0 10px rgba(168, 85, 247, 0.3);
}
.btn-contact {
    margin: 10px;
    padding: 10px 20px;
    border-radius: 8px;
    transition: all 0.3s ease;
    border: none;
    font-weight: 500;
    position: relative;
    overflow: hidden;
}
.btn-contact::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}
.btn-contact:hover::before {
    left: 100%;
}
.btn-primary {
    background: linear-gradient(45deg, #a855f7, #6366f1);
    box-shadow: 0 0 15px rgba(168, 85, 247, 0.3);
}
.btn-primary:hover {
    background: linear-gradient(45deg, #9333ea, #4f46e5);
    box-shadow: 0 0 25px rgba(168, 85, 247, 0.5);
    transform: translateY(-2px);
}
.btn-danger {
    background: linear-gradient(45deg, #ef4444, #dc2626);
    box-shadow: 0 0 15px rgba(239, 68, 68, 0.3);
}
.btn-danger:hover {
    background: linear-gradient(45deg, #dc2626, #b91c1c);
    box-shadow: 0 0 25px rgba(239, 68, 68, 0.5);
    transform: translateY(-2px);
}
.badge {
    background: linear-gradient(45deg, #a855f7, #6366f1);
    border: 1px solid rgba(255,255,255,0.1);
    box-shadow: 0 0 10px rgba(168, 85, 247, 0.2);
}

/* Responsive Design */
@media (max-width: 768px) {
    .search-box {
        width: 90%;
        padding: 12px 18px;
    }
    .tab-btn {
        padding: 6px 12px;
        font-size: 14px;
        margin: 2px;
    }
    .accordion-button {
        padding: 15px;
        font-size: 14px;
    }
    .footer-box {
        padding: 20px;
    }
    .btn-contact {
        display: block;
        width: 100%;
        margin: 5px 0;
    }
}

@media (max-width: 480px) {
    h1 {
        font-size: 1.8rem;
    }
    .hero-text {
        font-size: 16px;
    }
    .search-box {
        width: 95%;
    }
    .tab-btn {
        display: block;
        width: 100%;
        margin: 5px 0;
    }
}
</style>
</head>
<body>

<div class="container py-5 text-center">

  <!-- Title + Subtitle -->
  <h1 class="mb-3">Frequently Asked Questions</h1>
  <p class="hero-text">The most frequently asked questions and detailed answers. If you cannot find what you are looking for, please do not hesitate to contact us.</p>

  <!-- Search -->
  <div class="search-wrapper">
    <input type="text" id="faqSearch" class="search-box" placeholder="Enter the question you are looking for...">
  </div>

  <!-- Tabs -->
  <div class="text-center mb-4">
    <button class="tab-btn active" onclick="filterCategory('All')">All</button>
    <button class="tab-btn" onclick="filterCategory('Hacklinks')">Hacklinks</button>
    <button class="tab-btn" onclick="filterCategory('Account')">Account</button>
    <button class="tab-btn" onclick="filterCategory('Payments')">Payments</button>
  </div>

  <!-- Accordion -->
  <div class="accordion" id="faqAccordion">
    <?php foreach($faqs as $faq): 
      $cat = htmlspecialchars($faq['category']);
      $author = htmlspecialchars($faq['author']);
      $q = htmlspecialchars($faq['question']);
      $a = nl2br($faq['answer']);   // ✅ Answer এখন raw + nl2br, তাই সব show হবে
      $collapseId = "faq".$faq['id'];
    ?>
    <div class="accordion-item faq-card" data-category="<?=$cat?>">
      <h2 class="accordion-header" id="heading<?=$faq['id']?>">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?=$collapseId?>">
          <span class="badge bg-secondary me-2"><?=$author?></span> <?=$q?>
        </button>
      </h2>
      <div id="<?=$collapseId?>" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
        <div class="accordion-body"><?=$a?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Footer -->
  <div class="footer-box mt-5">
    <h3>Did you not find a solution?</h3>
    <p>If you cannot find your question in our FAQ section, you can contact us. Our technical team will assist you as soon as possible.</p>
    <a href="https://t.me/BL4CKHatSeo" class="btn btn-contact btn-primary">Telegram</a>
    <a href="destek-talep.php" class="btn btn-contact btn-danger">Support Ticket</a>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function filterCategory(cat){
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  event.target.classList.add('active');
  document.querySelectorAll('.accordion-item').forEach(item=>{
    if(cat==='All' || item.dataset.category===cat){
      item.style.display='block';
    }else{item.style.display='none';}
  });
}
document.getElementById('faqSearch').addEventListener('keyup',function(){
  let q=this.value.toLowerCase();
  document.querySelectorAll('.accordion-item').forEach(item=>{
    let txt=item.innerText.toLowerCase();
    item.style.display=txt.includes(q)?'block':'none';
  });
});
</script>
</body>
</html>