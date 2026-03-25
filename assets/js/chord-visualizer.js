class ChordVisualizer {
    constructor() {
        this.fretCount = 5;
        this.stringCount = 6;
        this.chordData = null;
    }

    async init() {
        try {
            const response = await fetch('assets/data/chords.json');
            this.chordData = await response.json();
        } catch (error) {
            console.error('Failed to load chord data:', error);
        }
    }

    normalizeChordName(name) {
        if (!name) return '';
        let n = name.trim();
        n = n.replace(/mi$/, 'm');
        n = n.replace(/mi([0-9])/, 'm$1');

        // Sync with backend normalization in admin_settings.php
        if (n === 'H') n = 'B';
        if (n === 'Hm') n = 'Bm';
        if (n === 'H7') n = 'B7';

        // From admin_settings.php normalize_chord function:
        n = n.replace(/^As(?!us)/i, 'Ab' + n.substring(2));
        n = n.replace(/^Es/i, 'Eb' + n.substring(2));
        n = n.replace(/([A-G][b#]?[m]?7?)4$/, '$1sus4');
        n = n.replace(/([A-G][b#]?[m]?7)sus$/, '$1sus4');
        n = n.replace('4sus', 'sus4');
        n = n.replace('7dim', 'dim7');
        n = n.replace('7maj', 'maj7');

        if (n.includes('maj') && !n.match(/maj[79]/)) n = n.replace('maj', 'maj7');
        n = n.replace('maj79', 'maj9');
        n = n.replace('maj77', 'maj7');

        if (n === 'E5b') n = 'Eb5';

        return n;
    }

    getChordInfo(name) {
        if (!this.chordData) return null;
        const normalized = this.normalizeChordName(name);
        let data = this.chordData[normalized] || this.chordData[name] || null;

        if (typeof data === 'string') {
            return { fingering: data, barre: null };
        }
        if (data && data.f) {
            return { fingering: data.f, barre: data.b };
        }
        return null;
    }

    render(name, chordInfo) {
        if (!chordInfo || !chordInfo.fingering) return '';

        const fingering = chordInfo.fingering;
        const frets = fingering.split(',').map(f => f.trim() === 'x' ? -1 : parseInt(f));

        const maxFret = Math.max(...frets);
        let startFret = 0;
        if (maxFret > 5) {
            const minPositiveFret = Math.min(...frets.filter(f => f > 0));
            startFret = minPositiveFret - 1;
        }

        const w = 150;
        const h = 220;
        const marginX = 35; // Side margins
        const marginTop = 45; // Top margin for labels
        const marginBottom = 20;

        const gridW = w - 2 * marginX;
        const gridH = h - marginTop - marginBottom;
        const stepX = gridW / (this.stringCount - 1);
        const stepY = gridH / this.fretCount;

        let svg = `<svg viewBox="0 0 ${w} ${h}" width="${w}" height="${h}" xmlns="http://www.w3.org/2000/svg" class="chord-svg">`;

        // Title (Chord Name)
        svg += `<text x="${w / 2}" y="${marginTop - 22}" text-anchor="middle" font-size="20" font-weight="bold" font-family="Arial" fill="currentColor">${name}</text>`;

        // Nut
        const nutWeight = startFret === 0 ? 6 : 2;
        svg += `<line x1="${marginX}" y1="${marginTop}" x2="${w - marginX}" y2="${marginTop}" stroke="currentColor" stroke-width="${nutWeight}" />`;

        // Vertical strings
        for (let i = 0; i < this.stringCount; i++) {
            const x = marginX + i * stepX;
            svg += `<line x1="${x}" y1="${marginTop}" x2="${x}" y2="${marginTop + gridH}" stroke="currentColor" stroke-width="1.5" />`;
        }

        // Horizontal frets
        for (let i = 1; i <= this.fretCount; i++) {
            const y = marginTop + i * stepY;
            svg += `<line x1="${marginX}" y1="${y}" x2="${w - marginX}" y2="${y}" stroke="currentColor" stroke-width="1" opacity="0.5" />`;
        }

        // Fret number
        if (startFret > 0) {
            svg += `<text x="${marginX - 25}" y="${marginTop + stepY / 1.5}" font-size="14" font-family="Arial" fill="currentColor">${startFret + 1}p</text>`;
        }

        // Barre line
        if (chordInfo.barre !== null) {
            const barreFret = chordInfo.barre;
            const displayBarreFret = barreFret - startFret;
            if (displayBarreFret >= 1 && displayBarreFret <= this.fretCount) {
                let firstString = -1;
                let lastString = -1;
                frets.forEach((f, i) => {
                    if (f === barreFret) {
                        if (firstString === -1) firstString = i;
                        lastString = i;
                    }
                });

                if (firstString !== -1 && lastString !== -1 && lastString > firstString) {
                    const bx1 = marginX + firstString * stepX;
                    const bx2 = marginX + lastString * stepX;
                    const by = marginTop + (displayBarreFret - 0.5) * stepY;
                    svg += `<rect x="${bx1 - 7}" y="${by - 7}" width="${bx2 - bx1 + 14}" height="14" rx="7" fill="currentColor" opacity="0.8" />`;
                }
            }
        }

        // Dots and X/O markers
        frets.forEach((fret, i) => {
            const x = marginX + i * stepX;
            if (fret === -1) {
                svg += `<text x="${x - 6}" y="${marginTop - 10}" font-size="16" font-family="Arial" fill="currentColor">×</text>`;
            } else if (fret === 0) {
                svg += `<circle cx="${x}" cy="${marginTop - 12}" r="5" fill="none" stroke="currentColor" stroke-width="1.5" />`;
            } else {
                const displayFret = fret - startFret;
                const y = marginTop + (displayFret - 0.5) * stepY;
                svg += `<circle cx="${x}" cy="${y}" r="8" fill="currentColor" />`;
            }
        });

        svg += `</svg>`;
        return svg;
    }
}
window.ChordVisualizer = new ChordVisualizer();
