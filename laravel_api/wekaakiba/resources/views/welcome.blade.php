<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>WEKA AKIBA - CERO CEPATEC</title>
<link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family:'Share Tech Mono', monospace;
    background:#01010a;
    color:#00ffcc;
    overflow-x:hidden;
    line-height:1.6;
    position:relative;
}

/* === Glitch / Neon Title === */
.neon-title {
    font-size:5rem;
    text-align:center;
    color:#00ffcc;
    position:relative;
    animation:flicker 1.5s infinite alternate;
}
.neon-title::before,
.neon-title::after {
    content:attr(data-text);
    position:absolute;
    left:0; top:0;
    color:#ff00ff;
    clip:rect(0, 900px, 0, 0);
}
.neon-title::after {
    color:#00ffff;
    animation:glitch 2s infinite linear alternate-reverse;
}
@keyframes flicker {
    0%,19%,21%,23%,25%,54%,56%,100%{opacity:1;}
    20%,24%,55%{opacity:0.2;}
}
@keyframes glitch {
    0%{clip:rect(0,9999px,0,0);transform:translate(0,0);}
    20%{clip:rect(20px,9999px,30px,0);transform:translate(-2px,-2px);}
    40%{clip:rect(5px,9999px,25px,0);transform:translate(2px,2px);}
    60%{clip:rect(15px,9999px,40px,0);transform:translate(-1px,1px);}
    80%{clip:rect(0,9999px,20px,0);transform:translate(1px,-1px);}
    100%{clip:rect(0,9999px,0,0);transform:translate(0,0);}
}

/* === Hero Section === */
.hero {
    min-height:100vh;
    display:flex;
    flex-direction:column;
    justify-content:center;
    align-items:center;
    text-align:center;
    padding:2rem;
}

/* === Terminal Effect === */
.terminal {
    background:rgba(0,0,0,0.6);
    border:1px solid #00ffcc33;
    padding:1rem 2rem;
    border-radius:0.5rem;
    font-family:'Roboto Mono', monospace;
    font-size:1.1rem;
    color:#00ffcc;
    text-align:left;
    max-width:800px;
    width:100%;
    min-height:150px;
    overflow:hidden;
    position:relative;
}
.cursor {
    display:inline-block;
    width:10px;
    background:#00ffcc;
    margin-left:2px;
    animation:blink 0.7s infinite;
}
@keyframes blink{0%,50%,100%{opacity:1;}25%,75%{opacity:0;}}

/* === Cards Glass + Neon Pulse === */
.cards {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
    gap:2rem;
    max-width:1200px;
    margin:4rem auto;
    padding:0 2rem;
}
.card {
    background:rgba(0,255,204,0.05);
    border:1px solid #00ffcc33;
    backdrop-filter:blur(8px);
    border-radius:1rem;
    padding:2rem;
    transition:transform 0.3s ease, box-shadow 0.3s ease;
    position:relative;
}
.card::before {
    content:'';
    position:absolute;
    top:-50%; left:-50%;
    width:200%; height:200%;
    background:linear-gradient(45deg,#00ffcc,#ff00ff,#00ffff,#ff0000,#00ffcc,#ff00ff);
    background-size:400% 400%;
    filter:blur(20px);
    opacity:0.2;
    border-radius:1rem;
    animation:neonPulse 6s linear infinite;
}
.card:hover {
    transform:translateY(-10px);
    box-shadow:0 0 30px #00ffccaa,0 0 60px #ff00ffaa;
    z-index:2;
}
@keyframes neonPulse {
    0%{background-position:0% 50%;}
    50%{background-position:100% 50%;}
    100%{background-position:0% 50%;}
}
.card h3 {
    font-family:'Roboto Mono', monospace;
    font-size:1.5rem;
    color:#00ffff;
    margin-bottom:1rem;
}
.card p { color:#00ffccbb; z-index:3; position:relative; }

/* === Footer === */
footer {
    text-align:center;
    padding:2rem;
    color:#00ffcc66;
    font-size:0.9rem;
}

/* === Animated Background Scanlines === */
.bg-lines {
    position:fixed;
    top:0; left:0;
    width:100%; height:100%;
    background-image: linear-gradient(0deg, rgba(0,255,204,0.05) 1px, transparent 1px),
                      linear-gradient(90deg, rgba(0,255,204,0.05) 1px, transparent 1px);
    background-size:50px 50px;
    z-index:-1;
    animation:moveGrid 20s linear infinite;
}
.bg-scanlines {
    position:fixed; top:0; left:0;
    width:100%; height:100%;
    background:repeating-linear-gradient(0deg, rgba(0,255,255,0.03), rgba(0,255,255,0.03) 2px, transparent 2px, transparent 4px);
    z-index:-1;
    animation:scanMove 1s linear infinite;
}
@keyframes moveGrid{0%{background-position:0 0,0 0;}100%{background-position:500px 500px,500px 500px;}}
@keyframes scanMove{0%{background-position:0 0;}100%{background-position:0 4px;}}

</style>
</head>
<body>

<div class="bg-lines"></div>
<div class="bg-scanlines"></div>

<section class="hero">
    <h1 class="neon-title" data-text="WEKA AKIBA">WEKA AKIBA</h1>
    <div class="terminal" id="terminal"></div>
</section>

<section class="cards">
    <div class="card">
        <h3>Cybersecurity</h3>
        <p>Protection militaire contre toutes les intrusions, surveillance constante et protocoles cryptographiques de pointe.</p>
    </div>
    <div class="card">
        <h3>FinTech & Banking</h3>
        <p>Transactions rapides, portefeuilles mobiles, opérateurs RDC intégrés, et contrôle total de vos finances.</p>
    </div>
    <div class="card">
        <h3>NAS & Data</h3>
        <p>Stockage et sauvegarde sécurisés, monitoring 24/7 et détection proactive de menaces.</p>
    </div>
    <div class="card">
        <h3>Hacking Éthique</h3>
        <p>Tests de pénétration réguliers et simulations avancées pour garantir la sécurité maximale.</p>
    </div>
    <div class="card">
        <h3>Analytics & AI</h3>
        <p>Détection d’anomalies et analyses intelligentes pour une gestion proactive et sécurisée de vos flux financiers.</p>
    </div>
    <div class="card">
        <h3>Innovation Continue</h3>
        <p>Une équipe dédiée à la veille technologique pour intégrer les dernières avancées fintech et cybersécurité.</p>
    </div>
</section>

<footer>
    &copy; 2025 CERO CEPATEC - WEKA AKIBA | Cybersecurity, FinTech & Banking
</footer>

<script>
const terminal=document.getElementById('terminal');
const messages=[
"Initializing CERO CEPATEC Systems...",
"Loading WEKA AKIBA modules: FinTech, Cybersecurity, NAS, AI...",
"Connecting to secure RDC mobile operators...",
"Encrypting all financial transactions...",
"Monitoring network for intrusions...",
"System ready. Welcome to WEKA AKIBA."
];
let i=0,j=0,currentMessage="",isDeleting=false;
function type(){
    if(i<messages.length){
        if(!isDeleting&&j<=messages[i].length){
            currentMessage=messages[i].substring(0,j++);
            terminal.innerHTML=currentMessage+'<span class="cursor"></span>';
            setTimeout(type,70);
        } else if(j>messages[i].length){
            isDeleting=true;
            setTimeout(type,1000);
        } else if(isDeleting&&j>=0){
            currentMessage=messages[i].substring(0,j--);
            terminal.innerHTML=currentMessage+'<span class="cursor"></span>';
            setTimeout(type,40);
        } else if(isDeleting&&j<0){
            isDeleting=false;
            i++;
            setTimeout(type,300);
        }
    }
}
type();
</script>

</body>
</html>
