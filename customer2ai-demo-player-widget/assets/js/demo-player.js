(function($) {
    'use strict';

    $(window).on('elementor/frontend/init', function() {
        elementorFrontend.hooks.addAction('frontend/element_ready/demo-player.default', function($scope) {
            const $widget = $scope.find('.customer2ai-demo-player-widget');
            const config = $widget.data('config');
            
            if (!config || Object.keys(config).length === 0) {
                console.error('No demo player configuration found');
                return;
            }
            
            initDemoPlayer($widget, config);
        });
    });

    function initDemoPlayer($widget, tabData) {
        console.log('Demo Player initialized', tabData);
        console.log('Widget element:', $widget);
        console.log('Play button:', $widget.find("#playPauseBtn")[0]);
        // ---- Elements
        const tabsRoot = $widget.find(".demo-player_tabs")[0];
        const playBtn = $widget.find("#playPauseBtn")[0];
        const restartBtn = $widget.find("#restartBtn")[0];
        const progressBar = $widget.find("#progressBar")[0];
        const timeCurrent = $widget.find("#timeCurrent")[0];
        const timeDuration = $widget.find("#timeDuration")[0];
        const transcriptWindow = $widget.find("#transcriptWindow")[0];
        const pillContainer = $widget.find("#pillContainer")[0];
        const agentChip = $widget.find(".speaker-indicator.agent")[0];
        const humanChip = $widget.find(".speaker-indicator.human")[0];
        const callerChip = $widget.find(".speaker-indicator.caller")[0];
        const demoOverlay = $widget.find("#demoOverlay")[0];

        // ---- State
        let audio = null;
        let currentTab = null;
        let rafId = null;
        let transcriptIndex = 0;
        let currentSpeaker = null;
        let currentTabIndex = 0;
        let handoffSeen = false;
        let demoStarted = false;
        let isFirstLoad = true;

        const bubbles = [];
        const turns = [];
        const shownPillIds = new Set();
        const pillEls = new Map();

        function setAgentChip(mode) {
            if (!agentChip || !humanChip) return;
            
            if (mode === "human") {
                agentChip.classList.add('is-hidden');
                humanChip.classList.remove('is-hidden');
            } else {
                agentChip.classList.remove('is-hidden');
                humanChip.classList.add('is-hidden');
            }
        }

        function updateOverlayImage(imageUrl) {
            if (!demoOverlay || !imageUrl) return;
            
            demoOverlay.style.setProperty('--overlay-next-image', `url("${imageUrl}")`);
            demoOverlay.classList.add('is-transitioning');
            
            setTimeout(() => {
                demoOverlay.style.backgroundImage = `url("${imageUrl}")`;
                demoOverlay.classList.remove('is-transitioning');
            }, 140);
        }

        function setOverlayImageImmediate(imageUrl) {
            if (!demoOverlay || !imageUrl) return;
            demoOverlay.style.backgroundImage = `url("${imageUrl}")`;
        }

        function onPlayStateChange(isPlaying) {
            const playIcon = playBtn.querySelector('.play-icon');
            const pauseIcon = playBtn.querySelector('.pause-icon');
            
            if (isPlaying) {
                playIcon.classList.remove('is-visible');
                pauseIcon.classList.add('is-visible');
                playBtn.setAttribute("aria-label", "Pause audio");
            } else {
                playIcon.classList.add('is-visible');
                pauseIcon.classList.remove('is-visible');
                playBtn.setAttribute("aria-label", "Play audio");
            }
        }

        // ---- Build tabs
        // const tabKeys = Object.keys(tabData);
        // ---- Get tabs (already rendered by PHP)
        const tabButtons = Array.from($widget.find('.demo-player_tabs-item'));
        // Tab click handlers
        tabButtons.forEach((tab, idx) => {
            tab.addEventListener("click", () => {
                if (currentTabIndex === idx) return;
                tabButtons.forEach((b) => {
                    b.classList.remove("active");
                    b.setAttribute("aria-selected", "false");
                    b.tabIndex = -1;
                });
                tab.classList.add("active");
                tab.setAttribute("aria-selected", "true");
                tab.tabIndex = 0;
                currentTabIndex = idx;
                tab.blur(); // ← ADD THIS LINE
                // Delay loadTab slightly to let the UI repaint
                setTimeout(() => {
                    loadTab(tab.dataset.tab);
                }, 0);
            });
        });

        // ---- Audio wiring
        function bindAudioEvents() {
            audio.addEventListener("loadedmetadata", () => {
                progressBar.max = audio.duration || 0;
                timeDuration.textContent = formatTime(audio.duration || 0);
                progressBar.setAttribute("aria-valuemax", (audio.duration || 0).toFixed(1));
                updateProgressFill();
                renderTranscriptAt(0, true);
                rebuildPillsAt(0);
            });

            audio.addEventListener("play", () => {
                onPlayStateChange(true);
                startLoop();
                
                if (!demoStarted && demoOverlay) {
                    demoOverlay.classList.add("is-off");
                    demoStarted = true;
                }
            });

            audio.addEventListener("pause", () => {
                onPlayStateChange(false);
                stopLoop();
            });

            audio.addEventListener("ended", () => {
                onPlayStateChange(false);
                stopLoop();
            });

            audio.addEventListener("timeupdate", () => {
                const t = audio.currentTime || 0;
                progressBar.setAttribute("aria-valuenow", t.toFixed(1));
                progressBar.setAttribute("aria-valuetext", formatTime(t));
                timeCurrent.textContent = formatTime(t);
            });

            audio.addEventListener("error", () => {
                console.error("Audio error:", audio.error);
            });
        }

        function loadTab(tabKey) {
            currentTab = tabKey;

            stopLoop();
            if (audio) {
                audio.pause();
                audio.src = "";
                audio.load();
            }

            handoffSeen = false;
            demoStarted = false;
            resetProgressUI();
            clearTranscript();
            resetPillsState();
            setAgentChip("ai");
            updateSpeakerHighlight(null, true);
            
            if (!isFirstLoad) {
                if (demoOverlay && demoOverlay.classList.contains('is-off')) {
                    demoOverlay.style.backgroundImage = 'none';
                }
                
                const imageUrl = tabData[tabKey]?.imageOverlay;
                if (imageUrl) {
                    updateOverlayImage(imageUrl);
                }
            }
            
            if (demoOverlay) {
                demoOverlay.classList.remove("is-off");
            }
            
            isFirstLoad = false;

            const src = tabData[tabKey]?.audioUrl;
            if (!src) {
                console.error(`No audio source for tab "${tabKey}"`);
                return;
            }

            audio = new Audio();
            audio.preload = "auto";
            audio.src = src;

            bindAudioEvents();
            audio.load();
        }

        // ---- Controls
        playBtn.addEventListener("click", async (e) => {
            e.preventDefault();
            console.log('Play button clicked');
            console.log('Audio object:', audio);
            console.log('Audio src:', audio?.src);
            console.log('Audio paused:', audio?.paused);
            
            if (!audio) {
                console.error('No audio object exists!');
                return;
            }
            try {
                if (audio.paused) {
                    await audio.play();
                } else {
                    audio.pause();
                }
            } catch (err) {
                console.error("Audio play failed:", err);
            }
        });

        if (demoOverlay) {
            demoOverlay.addEventListener("click", async () => {
                if (!audio) return;
                try {
                    if (audio.paused) {
                        await audio.play();
                    }
                } catch (err) {
                    console.error("Audio play failed:", err);
                }
            });
        }

        restartBtn.addEventListener("click", (e) => {
            e.preventDefault();
            if (!audio) return;
            
            audio.currentTime = 0;
            handoffSeen = false;
            setAgentChip("ai");
            updateSpeakerHighlight(null, true);
            renderTranscriptAt(0, true);
            rebuildPillsAt(0);
            progressBar.value = 0;
            timeCurrent.textContent = "0:00";
            progressBar.setAttribute("aria-valuenow", "0");
            progressBar.setAttribute("aria-valuetext", "0:00");
            updateProgressFill();
        });

        progressBar.addEventListener("input", () => {
            if (!audio) return;
            const v = parseFloat(progressBar.value);
            audio.currentTime = Number.isFinite(v) ? v : 0;
            renderTranscriptAt(audio.currentTime, true);
            rebuildPillsAt(audio.currentTime);
            updateProgressFill();
        });

        // ---- Transcript rendering
        function isNearBottom(el, tolerance = 80) {
            return el.scrollHeight - el.scrollTop - el.clientHeight <= tolerance;
        }

        function clearTranscript() {
            bubbles.length = 0;
            turns.length = 0;
            $(transcriptWindow).find(".bubble, .handoff-divider").remove();
        }

        function updateSpeakerHighlight(speaker, force = false) {
            currentSpeaker = speaker;
            setAgentChip(handoffSeen ? "human" : "ai");

            const visibleAgentChip = handoffSeen ? humanChip : agentChip;
            [visibleAgentChip, callerChip].forEach((indicator) => {
                if (!indicator) return;
                
                let isActive = false;
                if (speaker === "caller" && indicator === callerChip) {
                    isActive = true;
                } else if (speaker === "agent" && indicator === agentChip && !handoffSeen) {
                    isActive = true;
                } else if (speaker === "human" && indicator === humanChip && handoffSeen) {
                    isActive = true;
                }
                
                indicator.classList.toggle("active", isActive);
            });
        }

        function renderTranscriptAt(t, keepPinned = false) {
            const wasNearBottom = keepPinned ? isNearBottom(transcriptWindow, 120) : false;
            handoffSeen = false;
            clearTranscript();
            transcriptIndex = 0;
            const cues = getCues();

            while (transcriptIndex < cues.length && t >= cues[transcriptIndex].time) {
                const cue = cues[transcriptIndex];
                addOrAppendTurn(cue);
                transcriptIndex++;
            }

            highlightCurrentBubbleAtTime(t);
            updateSpeakerHighlight(currentSpeaker, true);

            if (wasNearBottom) {
                requestAnimationFrame(() => {
                    transcriptWindow.scrollTop = transcriptWindow.scrollHeight;
                });
            }
        }

        // sets transcription for rendering
        function getCues() {
            const tab = tabData[currentTab] || {};
            // Check if transcript is in the new detailed format with segments
            if (tab.transcript && tab.transcript.segments && Array.isArray(tab.transcript.segments)) {
                    // Convert detailed format to simple format
                    return tab.transcript.segments.map(segment => ({
                        time: segment.start_time,
                        speaker: segment.speaker.id === "speaker_0" ? "agent" : "caller",
                        text: segment.text.trim()
                    })
                );
            }
            
            // Otherwise use the simple array format
            return Array.isArray(tab.transcript) ? tab.transcript : [];
        }

        function highlightCurrentBubbleAtTime(t) {
            bubbles.forEach((b) => b.classList.remove("is-current"));
            if (!turns.length) return;
            let idx = -1;
            for (let i = 0; i < turns.length; i++) {
                if (turns[i].start <= t) idx = i;
                else break;
            }
            if (idx >= 0) turns[idx].el.classList.add("is-current");
        }

        function resetPillsState() {
            shownPillIds.clear();
            pillEls.forEach((el) => el.remove());
            pillEls.clear();
        }

        function rebuildPillsAt(t) {
            resetPillsState();
            const feats = collectFeatures().filter((f) => t >= f.start).sort((a, b) => a.start - b.start);
            feats.forEach((f) => {
                if (!shownPillIds.has(f.id)) createPill(f, true);
            });
        }

        function addNewPillsUpTo(t) {
            collectFeatures().forEach((f) => {
                if (t >= f.start && !shownPillIds.has(f.id)) createPill(f, true);
            });
        }

        function createPill(feature, prepend) {
            const el = document.createElement("div");
            el.className = "pill";
            el.setAttribute("role", "status");
            el.setAttribute("aria-label", feature.text);
            el.innerHTML = `${feature.icon ? `<img src="${feature.icon}" alt="" />` : ""}${feature.text}`;
            prepend ? pillContainer.prepend(el) : pillContainer.appendChild(el);
            shownPillIds.add(feature.id);
            pillEls.set(feature.id, el);
        }

        function collectFeatures() {
            const src = tabData[currentTab]?.features;
            if (!src) return [];
            if (Array.isArray(src)) return src.slice();
            const out = [];
            Object.keys(src).forEach((k) => {
                const arr = Array.isArray(src[k]) ? src[k] : [];
                arr.forEach((it) => {
                    if (it && typeof it.start === 'number' && it.id && it.text) out.push(it);
                });
            });
            return out;
        }

        // ---- Loop
        function tick() {
            const t = audio.currentTime || 0;

            if (progressBar.max !== (audio.duration || 0)) {
                progressBar.max = audio.duration || 0;
                timeDuration.textContent = formatTime(audio.duration || 0);
                progressBar.setAttribute("aria-valuemax", (audio.duration || 0).toFixed(1));
            }
            progressBar.value = t;
            timeCurrent.textContent = formatTime(t);
            updateProgressFill();

            const cues = getCues();
            let lastSpeaker = currentSpeaker;
            while (transcriptIndex < cues.length && t >= cues[transcriptIndex].time) {
                const cue = cues[transcriptIndex];
                addOrAppendTurn(cue);
                lastSpeaker = cue.speaker;
                transcriptIndex++;
            }

            updateSpeakerHighlight(lastSpeaker, true);
            addNewPillsUpTo(t);
            highlightCurrentBubbleAtTime(t);

            rafId = requestAnimationFrame(tick);
        }

        function startLoop() {
            cancelAnimationFrame(rafId);
            rafId = requestAnimationFrame(tick);
        }

        function stopLoop() {
            cancelAnimationFrame(rafId);
            rafId = null;
        }

        // ---- Turn builder
        function makeLine(text) {
            const span = document.createElement("span");
            span.className = "line";
            span.textContent = text;
            return span;
        }

        function insertHandoffDividerOnce() {
            if (transcriptWindow.querySelector(".handoff-divider")) return;
            const d = document.createElement("div");
            d.className = "handoff-divider";
            d.textContent = "Handoff to human";
            transcriptWindow.appendChild(d);
        }

        function addOrAppendTurn(cue) {
            const shouldStick = isNearBottom(transcriptWindow, 80);
            const lastTurn = turns[turns.length - 1];

            if (cue.speaker === "human" && !handoffSeen) {
                handoffSeen = true;
                insertHandoffDividerOnce();
                setAgentChip("human");
            }

            if (lastTurn && lastTurn.speaker === cue.speaker) {
                lastTurn.el.appendChild(makeLine(cue.text));
                lastTurn.end = cue.time;
                currentSpeaker = cue.speaker;
            } else {
                const el = document.createElement("div");
                el.className = `bubble ${cue.speaker}`;
                el.dataset.start = String(cue.time);
                el.dataset.end = String(cue.time);
                el.appendChild(makeLine(cue.text));
                transcriptWindow.appendChild(el);
                turns.push({ speaker: cue.speaker, start: cue.time, end: cue.time, el });
                bubbles.push(el);
                currentSpeaker = cue.speaker;
            }

            if (shouldStick) {
                requestAnimationFrame(() => {
                    transcriptWindow.scrollTop = transcriptWindow.scrollHeight;
                });
            }
        }

        // ---- UI helpers
        function resetProgressUI() {
            transcriptIndex = 0;
            currentSpeaker = null;
            timeCurrent.textContent = "0:00";
            timeDuration.textContent = "0:00";
            progressBar.value = 0;
            progressBar.setAttribute("aria-valuenow", "0");
            progressBar.setAttribute("aria-valuetext", "0:00");
            updateProgressFill();
            onPlayStateChange(false);
        }

        function formatTime(seconds) {
            const s = Math.max(0, Math.floor(seconds || 0));
            const m = Math.floor(s / 60);
            const r = s % 60;
            return `${m}:${String(r).padStart(2, "0")}`;
        }

        function updateProgressFill() {
            const max = parseFloat(progressBar.max || 0);
            const val = parseFloat(progressBar.value || 0);
            const pct = max > 0 ? (val / max) * 100 : 0;
            progressBar.style.background = `linear-gradient(
                to right,
                #d35224 0%,
                #d35224 ${pct}%,
                rgba(0,0,0,0.08) ${pct}%,
                rgba(0,0,0,0.08) 100%
            )`;
        }

        // ---- Init
        if (tabButtons.length) {
            const firstTabKey = tabButtons[0].dataset.tab;
            const firstImageUrl = tabData[firstTabKey]?.image;
            if (firstImageUrl) {
                setOverlayImageImmediate(firstImageUrl);
            }
            loadTab(firstTabKey);
        }
    }

})(jQuery);