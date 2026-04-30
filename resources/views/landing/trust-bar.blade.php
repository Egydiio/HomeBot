<div class="hb-trust-bar hb-reveal">
    <div class="hb-ts">
        <div class="hb-ts-num" data-count="2400">0</div>
        <div class="hb-ts-lbl">Casas ativas</div>
    </div>
    <div class="hb-ts-div"></div>
    <div class="hb-ts">
        <div class="hb-ts-num" data-count="48000">0</div>
        <div class="hb-ts-lbl">Notas processadas</div>
    </div>
    <div class="hb-ts-div"></div>
    <div class="hb-ts">
        <div class="hb-ts-num" data-count="12">0</div>
        <div class="hb-ts-lbl">Min. economizados por mês</div>
    </div>
    <div class="hb-ts-div"></div>
    <div class="hb-ts">
        <div class="hb-ts-num">R$0</div>
        <div class="hb-ts-lbl">Para começar</div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var countObs = new IntersectionObserver(function(entries) {
        entries.forEach(function(e) {
            if (!e.isIntersecting) return;
            var el = e.target;
            var target = parseInt(el.dataset.count);
            if (!target) return;
            var start = 0;
            var duration = 1400;
            var step = function(timestamp) {
                if (!start) start = timestamp;
                var progress = Math.min((timestamp - start) / duration, 1);
                var val = Math.floor(progress * target);
                el.textContent = target >= 1000 ? '+' + val.toLocaleString('pt-BR') : val + ' min';
                if (progress < 1) requestAnimationFrame(step);
                else el.textContent = target >= 1000 ? '+' + target.toLocaleString('pt-BR') : target + ' min';
            };
            requestAnimationFrame(step);
            countObs.unobserve(el);
        });
    }, { threshold: 0.5 });

    document.querySelectorAll('[data-count]').forEach(function(el) { countObs.observe(el); });

    var revealObs = new IntersectionObserver(function(entries) {
        entries.forEach(function(e) { if (e.isIntersecting) e.target.classList.add('in'); });
    }, { threshold: 0.12 });

    document.querySelectorAll('.hb-reveal').forEach(function(el) { revealObs.observe(el); });
})();
</script>
@endpush
