// Alpine component for the Hyperbeam "Sala" watch-party room (v2).
//
// Control is cooperative: exactly one controller at a time (tracked server-side in the
// Livewire component and exposed as $wire.controllerId). When this browser is NOT the
// controller — or the user toggled "travar mouse" — an overlay blocks the mouse and hides
// the cursor ONLY over the stream, leaving the rest of the site untouched.
//
// `@hyperbeam/web` is imported dynamically so Vite code-splits it onto this page only.
export function hbRoom({ embedUrl, myId }) {
    return {
        hb: null,
        myId,
        status: 'conectando…',

        // local (per-user) settings
        volume: 1,
        travar: false,
        playoutDelay: false,
        videoPaused: false,
        resolution: '1280x720',   // live resize (shared)

        // new-session parameters (settings popover + "Nova sessão" modal)
        fps: 30,
        baseResolution: '1280x720',

        _vis: null,

        // Reactively derived from the Livewire property, so the overlay follows control handoffs.
        get isController() { return this.$wire.controllerId === this.myId; },
        get showOverlay() { return !this.isController || this.travar; },

        async init() {
            const { default: Hyperbeam } = await import('@hyperbeam/web');

            this.hb = await Hyperbeam(this.$refs.screen, embedUrl, {
                volume: this.volume,
                onConnectionStateChange: (e) => { this.status = e?.state ?? 'conectado'; },
            });

            if (this.hb.width && this.hb.height) {
                this.resolution = `${this.hb.width}x${this.hb.height}`;
            }

            // Report tab visibility so others see us as present-but-translucent when we switch away.
            this._vis = () => this.$wire.setVisible(document.visibilityState === 'visible');
            document.addEventListener('visibilitychange', this._vis);
        },

        destroy() {
            if (this._vis) document.removeEventListener('visibilitychange', this._vis);
            this.hb?.destroy();
            this.hb = null;
        },

        applyVolume() { if (this.hb) this.hb.volume = this.volume; },
        applyPlayoutDelay() { if (this.hb) this.hb.playoutDelay = this.playoutDelay; },
        applyPause() { if (this.hb) this.hb.videoPaused = this.videoPaused; },
        reconnect() { this.hb?.reconnect(); },

        applyResolution() {
            if (!this.hb) return;
            const [w, h] = this.resolution.split('x').map(Number);
            if (this.hb.maxArea && w * h > this.hb.maxArea) {
                this.resolution = `${this.hb.width}x${this.hb.height}`;   // exceeds allocatable area
                return;
            }
            this.hb.resize(w, h);
        },

        toggleFullscreen() {
            if (!document.fullscreenElement) this.$refs.stage.requestFullscreen?.();
            else document.exitFullscreen?.();
        },

        // "Nova sessão" -> ask the room to vote; the server recreates the VM once >=80% agree.
        requestReset() {
            const [w, h] = this.baseResolution.split('x').map(Number);
            this.$wire.requestReset(this.fps, w, h);
        },
    };
}
