<section class="hb-hero-v2">
    <div class="hb-hero-glow-r" aria-hidden="true"></div>
    <div class="hb-hero-glow-l" aria-hidden="true"></div>

    {{-- LEFT —— headline + CTA --}}
    <div>
        <div class="hb-eyebrow"><span class="hb-eyebrow-dot"></span>Para quem divide casa</div>
        <h1 class="hb-hero-h1">Divida a casa,<br><span style="color:#1fcc8a">sem briga.</span></h1>
        <p class="hb-hero-sub">Manda a foto da nota no WhatsApp. O HomeBot lê tudo, separa o que é da casa, divide 50/50 e cobra no Pix. Automático.</p>
        <div class="hb-hero-actions">
            <a href="{{ route('register') }}" class="hb-btn-primary">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" style="flex-shrink:0" aria-hidden="true"><path d="M10 2C5.58 2 2 5.58 2 10c0 1.52.43 2.94 1.17 4.15L2 18l3.92-1.15A7.94 7.94 0 0010 18c4.42 0 8-3.58 8-8s-3.58-8-8-8z"/></svg>
                Começar no WhatsApp
            </a>
            <a href="#como" class="hb-btn-ghost">Ver como funciona ↓</a>
        </div>
        <div class="hb-hero-trust">
            <div class="hb-avatars">
                <div class="hb-av" style="background:#1a3d2a;color:#1fcc8a">J</div>
                <div class="hb-av" style="background:#1a2a4d;color:#4d7eff">A</div>
                <div class="hb-av" style="background:#2d1a3d;color:#a78bfa">P</div>
                <div class="hb-av" style="background:#3d2a1a;color:#f0a828">M</div>
            </div>
            <span style="font-size:12.5px;color:#5d6880;font-family:'IBM Plex Sans',sans-serif"><strong style="color:#edf0f8">+2.400 casas</strong> no Brasil já usam</span>
        </div>
    </div>

    {{-- RIGHT —— animated phone --}}
    <div class="hb-hero-right">
        <div class="hb-phone-wrap">
            <div class="hb-phone">
                <div class="hb-phone-notch"></div>
                <div class="hb-phone-screen">
                    <div class="wa-hdr">
                        <div class="wa-hdr-av">🤖</div>
                        <div>
                            <div class="wa-hdr-name">HomeBot</div>
                            <div class="wa-hdr-status"><span class="wa-hdr-dot"></span>online</div>
                        </div>
                    </div>
                    <div class="wa-msgs" id="hb-wa-chat"></div>
                    <div class="wa-bar">
                        <div class="wa-bar-inp">Mensagem</div>
                        <div class="wa-bar-send">📷</div>
                    </div>
                </div>
            </div>
            <div class="hb-phone-card" id="hb-phone-card">
                <div class="pc-label">Ana te deve</div>
                <div class="pc-amount">R$70,00</div>
                <div class="pc-sub">Saldo total · Abril 2025</div>
                <div class="pc-foot">
                    <div class="pc-mem">
                        <div class="pc-mem-av">A</div>
                        <div class="pc-mem-name">Ana Pereira</div>
                    </div>
                    <span class="pc-cobrar">Cobrar →</span>
                </div>
            </div>
        </div>
    </div>
</section>

@push('scripts')
<script>
(function () {
    var chat = document.getElementById('hb-wa-chat');
    var card = document.getElementById('hb-phone-card');
    if (!chat || !card) return;

    function makeRow(cls) {
        var d = document.createElement('div');
        d.className = 'wa-row' + (cls ? ' ' + cls : '');
        return d;
    }

    function makeBbl(type, html) {
        var b = document.createElement('div');
        b.className = 'wa-bbl ' + type;
        b.innerHTML = html;
        requestAnimationFrame(function() { requestAnimationFrame(function() { b.classList.add('show'); }); });
        return b;
    }

    function addTyping() {
        var row = makeRow('');
        var t = document.createElement('div');
        t.className = 'wa-typing';
        t.innerHTML = '<div class="wa-tdot"></div><div class="wa-tdot"></div><div class="wa-tdot"></div>';
        row.appendChild(t);
        chat.appendChild(row);
        requestAnimationFrame(function() { requestAnimationFrame(function() { t.classList.add('show'); }); });
        chat.scrollTop = 9999;
        return row;
    }

    function addMsg(type, html) {
        var row = makeRow(type === 'usr' ? 'u' : '');
        var b = makeBbl(type, html);
        row.appendChild(b);
        chat.appendChild(row);
        chat.scrollTop = 9999;
    }

    function sleep(ms) { return new Promise(function(r) { setTimeout(r, ms); }); }

    async function runDemo() {
        chat.innerHTML = '';
        card.classList.remove('on');
        await sleep(900);
        addMsg('usr', '<div class="wa-img-ph"><div class="ico">🧾</div><div class="cap">nota_extra.jpg · 2.4MB</div></div><div class="wa-ts">14:02 <span class="wa-ck">✓✓</span></div>');
        await sleep(1000);
        var typing = addTyping();
        await sleep(1500);
        typing.remove();
        addMsg('bot', '<div style="font-size:9.5px;color:rgba(255,255,255,.45);margin-bottom:6px;letter-spacing:.04em">📋 23 ITENS ENCONTRADOS</div><div class="wa-rcpt-row">Arroz Tio João 5kg<span style="font-family:monospace">R$28,50</span></div><div class="wa-rcpt-row">Cerveja Heineken 6un<span style="font-family:monospace">R$42,00</span></div><div class="wa-rcpt-row" style="color:rgba(255,255,255,.4)">+ 21 itens...</div><div class="wa-rcpt-total">Total<span style="font-family:monospace;color:#25d366">R$287,40</span></div><div class="wa-ts">14:02</div>');
        await sleep(900);
        addMsg('bot', 'Algum item é <strong>pessoal</strong>? 🏠 Responde aqui ou toca para separar.<div class="wa-ts">14:02</div>');
        await sleep(1200);
        addMsg('usr', 'A cerveja é minha 🍺<div class="wa-ts">14:03 <span class="wa-ck">✓✓</span></div>');
        await sleep(1000);
        addMsg('res', '✅ <strong>R$245,40 da casa</strong> dividido!<br><span class="wa-res-amount">Ana te deve +R$30,55</span><br><span style="font-size:9.5px;color:rgba(255,255,255,.4)">Saldo total: R$70,00 a receber</span><div class="wa-ts">14:03</div>');
        await sleep(700);
        card.classList.add('on');
        await sleep(5000);
        card.style.transition = 'opacity .4s';
        card.style.opacity = '0';
        await sleep(500);
        chat.style.transition = 'opacity .4s';
        chat.style.opacity = '0';
        await sleep(500);
        card.classList.remove('on');
        card.style.opacity = '';
        chat.style.opacity = '';
        chat.style.transition = '';
        card.style.transition = '';
        await sleep(400);
        runDemo();
    }

    runDemo();
})();
</script>
@endpush
