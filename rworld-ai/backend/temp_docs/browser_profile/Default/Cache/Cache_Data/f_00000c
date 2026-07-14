/*!
* UX4G Accessibility v2.22.0 (https://doc.ux4g.gov.in/)
* Copyright 2025 The UX4G Authors(Vipul Agarwal, Ershad Alam)
* Copyright 2025 NeGD, MeitY.
* Licensed under MIT. 
*/

(function () {
    const SETTINGS_KEY = "accessibilitySettings";
    const prefix = "ux4g-accessibility-";

    // Function to get cookie domain dynamically
    function getCookieDomain() {
        const hostname = location.hostname;

        // For localhost or IP addresses, return empty string
        if (hostname === 'localhost' || /^\d+\.\d+\.\d+\.\d+$/.test(hostname)) {
            return '';
        }

        // For regular domains, prepend with dot for cross-subdomain access
        const parts = hostname.split('.');

        if (parts.length > 1) {
            return '.' + parts.slice(1).join('.'); // ".example.co.in"
        }
    }

    const widgetHTML = `
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>UX4G Accessibility Tool</title>
<link rel="stylesheet" href="https://cdn.ux4g.gov.in/accessibility-v2.22/accessibility-widget.css">

</head>
<body class="accessibility-loading">

<div class="uwaw uw-light-theme gradient-head uwaw-initial paid_widget" id="uw-main" role="dialog" aria-modal="true" aria-labelledby="uw-heading">
    <div class="relative second-panel">
        <h2 id="uw-heading">Accessibility options <span class="inner-short-key">Ctrl+F2</span></h2>
        <button type="button" aria-label="Close main navigation panel" class="uwaw-close" tabindex="1"></button>
    </div>
    <div class="uwaw-body">
        <div class="h-scroll">
            <div class="uwaw-features">
            <div class="uwaw-features__item reset-feature" id="featureItem">
                    <button aria-label="Bigger Text" tabindex="2" id="btn-s9" class="uwaw-features__item__i">
                        <span class="uwaw-features__item__icon">
                            <span class="ux4g-icon icon-bigger-text" role="img" aria-label="Bigger text icon" aria-hidden="true" aria-pressed="false"></span>
                        </span>
                        <span class="uwaw-features__item__name">Bigger Text</span>
                        <div class="uwaw-features__item__steps reset-steps" id="featureSteps">
                            <span class="step uwaw-features__step"></span>
                            <span class="step uwaw-features__step"></span>
                            <span class="step uwaw-features__step"></span>
                            <span class="step uwaw-features__step"></span>
                        </div>
                        <span class="tick-active uwaw-features__item__enabled reset-tick" id="tickIcon"  aria-live="polite" role="status"></span>
                    </button>
                </div>
                <div class="uwaw-features__item reset-feature" id="featureItem-st">
                    <button aria-label="Smaller Text" tabindex="3" id="btn-s17" class="uwaw-features__item__i" disabled>
                        <span class="uwaw-features__item__icon">
                            <span class="ux4g-icon icon-smaller-text" role="img" aria-label="Smaller text icon" aria-hidden="true" aria-pressed="false"></span>
                        </span>
                        <span class="uwaw-features__item__name">Smaller Text</span>
                        <span class="tick-active uwaw-features__item__enabled reset-tick" id="tickIconSt"  aria-live="polite" role="status"></span>
                    </button>
                </div>

                 <div class="uwaw-features__item reset-feature" id="featureItem-ts">
                    <button aria-label="Text Spacing" tabindex="4" id="btn-s13" class="uwaw-features__item__i">
                        <span class="uwaw-features__item__icon">
                            <span class="ux4g-icon icon-text-spacing"  role="img" aria-label="Text spacing icon" aria-hidden="true" aria-pressed="false"></span>
                        </span>
                        <span class="uwaw-features__item__name">Text Spacing</span>
                        <div class="uwaw-features__item__steps reset-steps" id="featureSteps-ts">
                            <span class="step uwaw-features__step"></span>
                            <span class="step uwaw-features__step"></span>
                            <span class="step uwaw-features__step"></span>
                        </div>
                        <span class="tick-active uwaw-features__item__enabled reset-tick" id="tickIcon-ts"  aria-live="polite" role="status"></span>
                    </button>
                </div>

                 <div class="uwaw-features__item reset-feature" id="featureItem-lh">
                    <button aria-label="Line Height" tabindex="5" id="btn-s12" class="uwaw-features__item__i">
                        <span class="uwaw-features__item__icon">
                            <span class="ux4g-icon icon-line-hight" role="img" aria-label="Line height icon" aria-hidden="true" aria-pressed="false"></span>
                        </span>
                        <span class="uwaw-features__item__name">Line Height</span>
                        <div class="uwaw-features__item__steps reset-steps" id="featureSteps-lh">
                            <span class="step uwaw-features__step"></span>
                            <span class="step uwaw-features__step"></span>
                            <span class="step uwaw-features__step"></span>
                            <span class="step uwaw-features__step"></span>
                        </div>
                        <span class="tick-active uwaw-features__item__enabled reset-tick" id="tickIcon-lh"  aria-live="polite" role="status"></span>
                    </button>
                </div>

                 <div class="uwaw-features__item reset-feature" id="featureItem-df">
                    <button aria-label="Dyslexia Friendly Font" aria-pressed="false" tabindex="6" id="btn-df" class="uwaw-features__item__i">
                        <span class="uwaw-features__item__icon">
                            <span class="ux4g-icon icon-dyslexia-font" role="img" aria-label="Dyslexia friendly font icon" aria-hidden="true"></span>
                        </span>
                        <span class="uwaw-features__item__name">Dyslexia Friendly</span>
                        <span class="tick-active uwaw-features__item__enabled reset-tick" id="tickIcon-df"  aria-live="polite" role="status"></span>
                    </button>
                </div>
                <div class="uwaw-features__item reset-feature" id="featureItem-adhd">                    
                    <button aria-label="ADHD Mode" aria-pressed="false" tabindex="7" id="btn-adhd" class="uwaw-features__item__i">
                        <span class="uwaw-features__item__icon">
                            <span class="ux4g-icon icon-adhd-friendly" role="img" aria-label="Icon ADHD Friendly icon" aria-hidden="true"></span>
                        </span>
                        <span class="uwaw-features__item__name">ADHD Mode</span>
                        <span class="tick-active uwaw-features__item__enabled reset-tick" id="tickIcon-adhd" aria-live="polite" role="status"></span>
                    </button>
                </div>
                <!-- Saturate Widget -->
                <div class="uwaw-features__item reset-feature" id="featureItem-saturate">
                    <button aria-label="Saturate Colors" aria-pressed="false" tabindex="8" id="btn-saturate" class="uwaw-features__item__i">
                        <span class="uwaw-features__item__icon">
                            <span class="ux4g-icon icon-saturate" role="img" aria-label="Saturate icon" aria-hidden="true" id="saturate-text-0"></span>
                            <span class="ux4g-icon icon-lowsaturate" role="img" aria-label="Low Saturate icon" aria-hidden="true" id="saturate-text-1" style="display: none;"></span>
                            <span class="ux4g-icon icon-highsaturate" role="img" aria-label="High Saturate colors icon" aria-hidden="true" id="saturate-text-2" style="display: none;"></span>
                            <span class="ux4g-icon icon-desaturate" role="img" aria-label="Desaturate icon" aria-hidden="true" id="saturate-text-3" style="display: none;"></span>

                        </span>
                        <div id="feature-container">
                            <span class="uwaw-features__item__name saturate-text" id="saturate-detail-text-0">Saturation</span>
                            <span class="uwaw-features__item__name saturate-text" id="saturate-detail-text-1" style="display: none;">Low Saturation</span>
                            <span class="uwaw-features__item__name saturate-text" id="saturate-detail-text-2" style="display: none;">High Saturation</span>
                            <span class="uwaw-features__item__name saturate-text" id="saturate-detail-text-3" style="display: none;">Desaturate</span>
                        </div>
                        <div class="uwaw-features__item__steps reset-steps" id="featureStepsSaturate">
                            <span class="step uwaw-features__step"></span>
                            <span class="step uwaw-features__step"></span>
                            <span class="step uwaw-features__step"></span>
                        </div>
                        <span class="tick-active uwaw-features__item__enabled reset-tick" id="tickIcon-saturate"  aria-live="polite" role="status"></span>
                    </button>
                </div>
<div class="uwaw-features__item reset-feature" id="featureItem-ht-dark">
                    <button aria-label="Light Dark Theme" aria-pressed="false" tabindex="9" id="dark-btn" class="uwaw-features__item__i">
                        <div class="uwaw-features__item__name">
                            <div class="light_dark_icon">
                                <input type="checkbox" class="light_mode uwaw-featugres__item__i" id="checkbox"  aria-label="Toggle light and dark mode" role="switch"/>
                                <label for="checkbox" class="checkbox-label">
                                    <i class="fas fa-moon-stars">
                                        <span class="ux4g-icon icon-moon" role="img" aria-label="Dark mode icon" aria-hidden="true"></span>
                                    </i>
                                    <i class="fas fa-sun">
                                        <span class="ux4g-icon icon-sun" role="img" aria-label="Light mode icon" aria-hidden="true"></span>
                                    </i>
                                    <span class="ball"></span>
                                </label>
                            </div>
                            <span class="uwaw-features__item__name">Light-Dark</span>
                        </div>
                        <span class="tick-active uwaw-features__item__enabled reset-tick" id="tickIcon-ht-dark"  aria-live="polite" role="status"></span>
                    </button>
                </div>
                <!-- Invert Colors Widget -->
                <div class="uwaw-features__item reset-feature" id="featureItem-ic">
                    <button aria-label="Invert Colors" aria-pressed="false" tabindex="10" id="btn-invert" class="uwaw-features__item__i">
                        <span class="uwaw-features__item__icon">
                            <span class="ux4g-icon icon-invert" role="img" aria-label="Invert colors icon" aria-hidden="true"></span>
                        </span>
                        <span class="uwaw-features__item__name">Invert Colors</span>
                        <span class="tick-active uwaw-features__item__enabled reset-tick" id="tickIcon-ic"  aria-live="polite" role="status"></span>
                    </button>
                </div>
 <div class="uwaw-features__item reset-feature" id="featureItem-ht">
                    <button aria-pressed="false" aria-label="Highlight Links" tabindex="11" id="btn-s10" class="uwaw-features__item__i">
                        <span class="uwaw-features__item__icon">
                            <span class="ux4g-icon icon-highlight-links" role="img" aria-label="Highlight links icon" aria-hidden="true"></span>
                        </span>
                        <span class="uwaw-features__item__name">Highlight Links</span>
                        <span class="tick-active uwaw-features__item__enabled reset-tick" id="tickIcon-ht"  aria-live="polite" role="status"></span>
                    </button>
                </div>
                
                <div class="uwaw-features__item reset-feature" id="featureItem_sp">
                    <button aria-label="Text To Speech" tabindex="12" id="speak" class="uwaw-features__item__i">
                        <span class="uwaw-features__item__icon">
                            <span class="ux4g-icon icon-speaker" role="img" aria-label="Text to speech icon" aria-hidden="true" aria-pressed="false"></span>
                        </span>
                        <span class="uwaw-features__item__name">Text To Speech</span>
                        <span class="tick-active uwaw-features__item__enabled reset-tick" id="tickIcon_sp"  aria-live="polite" role="status"></span>
                    </button>
                </div>
               
                <div class="uwaw-features__item reset-feature" id="featureItem-Cursor">
                    <button aria-label="Cursor Bigger" aria-pressed="false" tabindex="13" id="btn-cursor" class="uwaw-features__item__i">
                        <span class="uwaw-features__item__icon">
                            <span class="ux4g-icon icon-cursor" role="img" aria-label="Cursor bigger icon" aria-hidden="true"></span>
                        </span>
                        <span class="uwaw-features__item__name">Cursor</span>
                        <span class="tick-active uwaw-features__item__enabled reset-tick" id="tickIcon-cursor"  aria-live="polite" role="status"></span>
                    </button>
                </div>
                
                <div class="uwaw-features__item reset-feature" id="featureItem-animate">
                    <button aria-label="Pause Animation" aria-pressed="false" tabindex="14" id="btn-animation" class="uwaw-features__item__i">
                        <span class="uwaw-features__item__icon">
                            <span class="ux4g-icon icon-animation-pause" role="img" aria-label="Pause Animation icon" aria-hidden="true"></span>
                        </span>
                        <span class="uwaw-features__item__name">Pause Animation</span>
                        <span class="tick-active uwaw-features__item__enabled reset-tick" id="tickIcon-animate"  aria-live="polite" role="status"></span>
                    </button>
                </div>
                 <div class="uwaw-features__item reset-feature" id="featureItem-hi">
                    <button aria-label="Hide Images" aria-pressed="false" tabindex="15" id="btn-s11" class="uwaw-features__item__i">
                        <span class="uwaw-features__item__icon">
                            <span class="ux4g-icon icon-hide-images" role="img" aria-label="Hide images icon" aria-hidden="true"></span>
                        </span>
                        <span class="uwaw-features__item__name">Hide Images</span>
                        <span class="tick-active uwaw-features__item__enabled reset-tick" id="tickIcon-hi"  aria-live="polite" role="status"></span>
                    </button>
                </div>


               
            </div>
        </div>
        
        <!-- Reset Button -->
    </div>
    <div class="reset-panel">
        <!-- copyright accessibility bar -->
        <div class="copyrights-accessibility">
            <button aria-label="Reset All Settings" tabindex="16" class="btn-reset-all" id="reset-all">
                <div class="reset-icon"></div>
                <div class="reset-btn-text">Reset All Settings</div>
            </button>
            <a tabindex="-1" href="https://www.ux4g.gov.in" target="_blank" class="copyright-text" contenteditable="false" style="cursor: pointer;">
                <span class="uwaw-features__item__name ux4g-copy ux4g-copyright">Created by</span>
                <span class="ux4g-logo"></span>
            </a>
        </div>
    </div>
</div>
<button tabindex="1"  aria-label="Accessibility Options" data-uw-trigger="true" aria-haspopup="dialog" aria-controls="uw-main" aria-expanded="false" id="uw-widget-custom-trigger" class="uw-widget-custom-trigger">
    <label class="short-key">Ctrl+F2</label>    
    <img alt="icon" loading="lazy"
        src="data:image/svg+xml,%0A%3Csvg width='32' height='32' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cg clip-path='url(%23clip0_1_1506)' fill='%23fff'%3E%3Cpath d='M16 7a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7z'/%3E%3Cpath d='M27 7.05l-.028.008-.026.008a7.112 7.112 0 0 0-.188.055c-1.163.341-6.808 1.933-10.785 1.933-3.696 0-8.83-1.375-10.472-1.842A4.61 4.61 0 0 0 5 7.05c-1.188-.313-2 .893-2 1.996 0 1.092.98 1.612 1.972 1.985v.017l5.95 1.86c.609.232.771.47.85.677.259.662.053 1.972-.02 2.43l-.363 2.812L9.378 29.84l-.017.092-.014.08c-.145 1.009.596 1.988 2 1.988 1.225 0 1.766-.846 2-1.996.234-1.15 1.75-9.848 2.625-9.848s2.677 9.848 2.677 9.848c.235 1.15.775 1.996 2 1.996 1.408 0 2.15-.984 2-1.996a3.591 3.591 0 0 0-.047-.254l-2.04-10.92-.361-2.813c-.262-1.638-.052-2.18.02-2.306a.07.07 0 0 0 .005-.01c.067-.125.375-.405 1.092-.674l5.58-1.95c.034-.01.068-.02.101-.033 1-.375 2-.894 2-1.996 0-1.102-.811-2.31-1.999-1.998z'/%3E%3C/g%3E%3Cdefs%3E%3CclipPath id='clip0_1_1506'%3E%3Cpath fill='%23fff' d='M0 0h32v32H0z'/%3E%3C/clipPath%3E%3C/defs%3E%3C/svg%3E"
    />
    <span>Accessibility Options</span> 
</button>


<div id="accessibility-overlay">
    <button  id="open-the-accessibility-menu" class="skip-link" >
        <span class="icon-open-accessibile">
            <img alt="icon" loading="lazy" src="data:image/svg+xml,%0A%3Csvg width='32' height='32' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cg clip-path='url(%23clip0_1_1506)' fill='%23fff'%3E%3Cpath d='M16 7a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7z'/%3E%3Cpath d='M27 7.05l-.028.008-.026.008a7.112 7.112 0 0 0-.188.055c-1.163.341-6.808 1.933-10.785 1.933-3.696 0-8.83-1.375-10.472-1.842A4.61 4.61 0 0 0 5 7.05c-1.188-.313-2 .893-2 1.996 0 1.092.98 1.612 1.972 1.985v.017l5.95 1.86c.609.232.771.47.85.677.259.662.053 1.972-.02 2.43l-.363 2.812L9.378 29.84l-.017.092-.014.08c-.145 1.009.596 1.988 2 1.988 1.225 0 1.766-.846 2-1.996.234-1.15 1.75-9.848 2.625-9.848s2.677 9.848 2.677 9.848c.235 1.15.775 1.996 2 1.996 1.408 0 2.15-.984 2-1.996a3.591 3.591 0 0 0-.047-.254l-2.04-10.92-.361-2.813c-.262-1.638-.052-2.18.02-2.306a.07.07 0 0 0 .005-.01c.067-.125.375-.405 1.092-.674l5.58-1.95c.034-.01.068-.02.101-.033 1-.375 2-.894 2-1.996 0-1.102-.811-2.31-1.999-1.998z'/%3E%3C/g%3E%3Cdefs%3E%3CclipPath id='clip0_1_1506'%3E%3Cpath fill='%23fff' d='M0 0h32v32H0z'/%3E%3C/clipPath%3E%3C/defs%3E%3C/svg%3E">
        </span>
        <strong>Open the accessibility option</strong>
        <span class="icon-enter"></span>
    </button>
</div>
</body>
</html>`;
    document.body.insertAdjacentHTML('beforeend', widgetHTML);
    document.addEventListener("DOMContentLoaded", loadSettings);
    document.addEventListener("scroll", function () {
        detectRouteChange()
    })
    document.getElementById('uw-widget-custom-trigger').addEventListener('click', function () {
        document.getElementById('uw-main').style.right = '0'
    });
    document.getElementById('open-the-accessibility-menu').addEventListener('click', function () {
        const menu = document.getElementById('uw-main');
        const btn = document.getElementById('open-the-accessibility-menu');
        if (menu) {
            menu.style.right = '0';
        }
        if (btn) {
            btn.style.display = 'none';
        }
    });

    function closeMain() {
        document.getElementById('uw-main').style.right = '-530px'
    }
    document.addEventListener('DOMContentLoaded', function () {
        const closeButtons = document.querySelectorAll('.uwaw-close');
        closeButtons.forEach(function (button) {
            button.addEventListener('click', closeMain)
        })
    });
    let fontSizeCount = 0;
    let lineHeightCount = 0;
    let textSpacingCount = 0;
    let saturateCount = 0;
    let lastPath = window.location.pathname;
    let screenReader = !1;
    const fontSizeSpans = document.querySelectorAll('#featureSteps span');
    const lineHeightSpans = document.querySelectorAll('#featureSteps-lh span');
    const textSpacingSpans = document.querySelectorAll('#featureSteps-ts span');
    const saturateSpans = document.querySelectorAll('#featureStepsSaturate span');
    let speechSynthesisInstance = window.speechSynthesis;
    let tabPressCount = 0;
    let adhdActive = false;

    // Create the reading mask
    const readingMask = document.createElement('div');
    readingMask.className = 'reading-mask-horizontal';

    document.body.appendChild(readingMask);

    // Track cursor Y position
    let cursorPositionY = 0;

    document.addEventListener('mousemove', (e) => {
        cursorPositionY = e.clientY;
        updateHighlightBarPosition();
        readingMask.style.setProperty('--y', `${e.clientY}px`);
    });

    window.addEventListener('wheel', () => {
        updateHighlightBarPosition();
    });

    function updateHighlightBarPosition() {
        if (adhdActive) {
            readingMask.style.top = '0';
        }
    }

    function speakText(text) {
        if (!text.trim()) return;
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = "en-US";
        utterance.rate = 1;
        speechSynthesisInstance.cancel();
        speechSynthesisInstance.speak(utterance)
    }
    // document.addEventListener("keydown", (event) => {
    //     if (event.key === "Tab") {
    //         tabPressCount++;
    //         if (tabPressCount === 2) {
    //             speakText("Press Enter to open accessibility menu.")
    //         }
    //     } else if (event.key === "Enter" && tabPressCount === 2) {
    //         speakText("Opening accessibility menu.");
    //         tabPressCount = 0
    //     }
    // });

    // new this is for open panel on tab 




    document.addEventListener("DOMContentLoaded", () => {
        const accessibilityBtn = document.getElementById("open-the-accessibility-menu");
        const accessibilityMenu = document.getElementById("uw-main");
        const overlay = document.getElementById("accessibility-overlay");
        const closeBtn = document.getElementById("close-uw-main");

        if (!accessibilityBtn || !accessibilityMenu || !overlay) {
            // console.warn("Missing required elements.");
            return;
        }

        let hasSpoken = false;
        let menuOpened = false;
        let lastFocusedElement = null;
        let tabPressCount = 0;
        let menuClosedByEscape = false; // NEW FLAG

        function speakText(text) {
            const utterance = new SpeechSynthesisUtterance(text);
            speechSynthesis.speak(utterance);
        }

        // ============= TAB handler (initial entry) =============
        document.addEventListener("keydown", function (e) {
            if (e.key !== "Tab" || menuOpened) return;

            // 🚫 Don't show overlay again if closed by Escape
            if (menuClosedByEscape) return;

            tabPressCount++;

            if (tabPressCount === 1) {
                e.preventDefault();
                overlay.style.display = "flex";
                accessibilityBtn.style.visibility = "visible";
                accessibilityBtn.focus();

                if (!hasSpoken) {
                    speakText("Press Enter to open accessibility option, or press Tab again to continue");
                    hasSpoken = true;
                }
            } else if (tabPressCount === 2) {
                e.preventDefault();
                overlay.style.display = "none";
                accessibilityBtn.style.display = "none";
            }
        });

        // ============= Menu Control =============
        function openMenu() {
            lastFocusedElement = document.activeElement;

            accessibilityMenu.style.right = "0";
            overlay.style.visibility = "hidden";
            accessibilityBtn.style.visibility = "hidden";
            menuOpened = true;
            menuClosedByEscape = false; // Reset when menu is opened

            const focusableElements = accessibilityMenu.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            const firstFocusable =
                focusableElements.length > 0 ? focusableElements[0] : accessibilityMenu;

            if (firstFocusable === accessibilityMenu) {
                accessibilityMenu.setAttribute("tabindex", "-1");
            }
            firstFocusable.focus();
            tabPressCount = 0;
        }

        function closeMenu() {
            accessibilityMenu.style.right = "-530px";
            overlay.style.display = "none";
            accessibilityBtn.style.display = "none";
            tabPressCount = 0;
            menuOpened = false;
            menuClosedByEscape = true; // Mark as closed by Escape or manual close

            if (lastFocusedElement) {
                lastFocusedElement.focus();
            }
        }

        accessibilityBtn.addEventListener("keydown", (e) => {
            if (e.key === "Enter" || e.key === " ") {
                e.preventDefault();
                openMenu();
            }
        });

        accessibilityBtn.addEventListener("click", openMenu);

        if (closeBtn) {
            closeBtn.addEventListener("click", closeMenu);
        }

        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape") {
                closeMenu();
            }
        });

        overlay.addEventListener("click", (e) => {
            if (e.target === overlay) {
                closeMenu();
            }
        });

        // == Ctrl + F2 (toggle) ==
        document.addEventListener("keydown", (e) => {
            if (e.ctrlKey && e.key === "F2") {
                e.preventDefault();
                if (menuOpened) {
                    closeMenu();
                } else {
                    openMenu();
                }
            }
        });
    });





    // this is for open the accessibility menu  end here

    document.addEventListener("click", (event) => {
        tabPressCount = 0;
        let element = event.target;
        let clickedText = "";
        if (element.tagName === "IMG") {
            clickedText = element.getAttribute("alt") || element.getAttribute("aria-label") || element.getAttribute("title") || "Clickable image"
        } else {
            clickedText = element.innerText.trim()
        }
        if (clickedText && screenReader) {
            speakText(clickedText)
        }
    });
    document.addEventListener("mouseup", () => {
        let selectedText = window.getSelection().toString();
        if (selectedText && screenReader) {
            speakText(selectedText)
        }
    });
    document.addEventListener("mouseover", (event) => {
        let element = event.target;
        let textToSpeak = "";
        if (element.tagName === "IMG" && element.closest("A, BUTTON")) {
            textToSpeak = element.getAttribute("alt") || element.getAttribute("aria-label") || element.getAttribute("title") || "Clickable image"
        } else if (element.tagName === "A" || element.tagName === "BUTTON" || element.tagName === "INPUT" || element.tagName === "TEXTAREA" || element.hasAttribute("role")) {
            textToSpeak = element.innerText || element.getAttribute("aria-label") || element.getAttribute("alt") || element.value || "Interactive element"
        }
        if (textToSpeak && screenReader) {
            speechSynthesisInstance.cancel();
            speakText(textToSpeak)
        }
    });

    function toggleTextToSpeech() {
        const tickIcon = document.getElementById('tickIcon_sp');
        const button = document.getElementById('featureItem_sp');
        button.classList.toggle(prefix + 'feature-active');
        tickIcon.style.display = tickIcon.style.display === 'inline-flex' ? 'none' : 'inline-flex';
        screenReader = !screenReader;
        saveSettings();
        if (speechSynthesis.speaking) {
            speechSynthesis.cancel()
        }
    }
    document.addEventListener('DOMContentLoaded', function () {
        const speakButton = document.getElementById('speak');
        if (speakButton) {
            speakButton.addEventListener('click', toggleTextToSpeech)
        }
    });

    function applyTextSettings(direction = '+') {
        let elements = document.querySelectorAll('body > *:not(.uwaw)');
        elements.forEach(el => {
            let currentZoom = parseFloat(el.style.zoom) || 1;
            let newZoom;

            if (direction === '+') {
                newZoom = (currentZoom + 0.1).toFixed(2);
            } else {
                newZoom = Math.max(0.1, currentZoom - 0.1).toFixed(2); // Prevent zoom from going below 0.1
            }

            el.setAttribute('style', `zoom: ${newZoom} !important;`);
        });
    }

    function adjustFontSize(data) {
        if (data === '+1') {
            fontSizeCount = (fontSizeCount + 1) % 5;
            applyTextSettings('+');
        } else {
            fontSizeCount = (fontSizeCount - 1 + 5) % 5; // ensures no negative values
            if (fontSizeCount > 0) {
                applyTextSettings('-');
            }
        }

        const button = document.getElementById('featureItem');
        const tickIcon = document.getElementById('tickIcon');
        const fontCheck = document.getElementById('featureSteps');
        const smallerTextBtn = document.getElementById('btn-s17');

        button.classList.add(prefix + 'feature-active');

        saveSettings();

        // Enable or disable Smaller Text button based on fontSizeCount
        if (fontSizeCount > 0) {
            smallerTextBtn.disabled = false;
        } else {
            smallerTextBtn.disabled = true;
        }

        if (fontSizeCount === 0) {
            button.classList.toggle(prefix + 'feature-active');
            tickIcon.style.display = 'none';
            fontCheck.classList.remove(prefix + 'featureSteps-visible');
            let elements = document.querySelectorAll('body > *:not(.uwaw)');
            elements.forEach(el => el.style.zoom = 1);
            fontSizeSpans.forEach(span => span.classList.remove(prefix + 'active'))
        } else {
            tickIcon.style.display = 'inline-flex';
            fontCheck.classList.add(prefix + 'featureSteps-visible');
            fontSizeSpans.forEach(span => span.classList.remove(prefix + 'active'));
            fontSizeSpans.forEach((span, index) => {
                if (index <= fontSizeCount - 1) {
                    span.classList.add(prefix + 'active')
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const btnIncrease = document.getElementById('btn-s9');
        const btnDecrease = document.getElementById('btn-s17');

        if (btnIncrease) {
            btnIncrease.addEventListener('click', () => adjustFontSize('+1'));
        }
        if (btnDecrease) {
            btnDecrease.addEventListener('click', () => adjustFontSize('-1'));
        }
    });

    function createLineHeightStyles() {
        // Remove existing line height styles if they exist
        const existingStyle = document.getElementById('ux4g-line-height-styles');
        if (existingStyle) {
            existingStyle.remove();
        }

        // Create new style element
        const style = document.createElement('style');
        style.id = 'ux4g-line-height-styles';

        // Create dynamic CSS class based on current lineHeightCount
        if (lineHeightCount > 0) {
            const lineHeightValue = 1.2 + (lineHeightCount * 0.3); // Base 1.2 + incremental increase
            style.textContent = `
                .ux4g-line-height {
                    line-height: ${lineHeightValue} !important;
                }
            `;
        }

        document.head.appendChild(style);
    }

    // Updated updateLineHeight function to use CSS classes
    function updateLineHeight() {
        // Remove existing line height classes from all elements
        document.querySelectorAll('.ux4g-line-height').forEach((el) => {
            el.classList.remove('ux4g-line-height');
        });

        if (lineHeightCount > 0) {
            // Create/update CSS class
            createLineHeightStyles();

            // Add class to text elements
            const textElements = document.querySelectorAll('p:not(.uwaw *):not(.uwaw), h1:not(.uwaw *):not(.uwaw), h2:not(.uwaw *):not(.uwaw), h3:not(.uwaw *):not(.uwaw), h4:not(.uwaw *):not(.uwaw), h5:not(.uwaw *):not(.uwaw), h6:not(.uwaw *):not(.uwaw), div:not(.uwaw *):not(.uwaw), span:not(.uwaw *):not(.uwaw), li:not(.uwaw *):not(.uwaw), td:not(.uwaw *):not(.uwaw), th:not(.uwaw *):not(.uwaw)');

            // Use requestAnimationFrame for better performance
            requestAnimationFrame(() => {
                textElements.forEach((el) => {
                    // Skip elements that are hidden or have no content
                    if (el.offsetHeight === 0 || !el.textContent.trim()) return;

                    el.classList.add('ux4g-line-height');
                });
            });
        } else {
            // Remove the style element if lineHeightCount is 0
            const existingStyle = document.getElementById('ux4g-line-height-styles');
            if (existingStyle) {
                existingStyle.remove();
            }
        }
    }

    function adjustLineHeight() {
        const button = document.getElementById('featureItem-lh');

        button.classList.add(prefix + 'feature-active');
        lineHeightCount = (lineHeightCount + 1) % 5;

        if (lineHeightCount !== 0) {
            updateLineHeight();
        }

        saveSettings();

        const tickIcon = document.getElementById('tickIcon-lh');
        const lineHeightCheck = document.getElementById('featureSteps-lh');

        if (lineHeightCount === 0) {
            button.classList.remove(prefix + 'feature-active');
            tickIcon.style.display = 'none';
            lineHeightCheck.classList.remove(prefix + 'featureSteps-visible');

            // Remove classes instead of inline styles
            document.querySelectorAll('.ux4g-line-height').forEach((el) => {
                el.classList.remove('ux4g-line-height');
            });

            // Remove the style element
            const existingStyle = document.getElementById('ux4g-line-height-styles');
            if (existingStyle) {
                existingStyle.remove();
            }

            lineHeightSpans.forEach(span => span.classList.remove(prefix + 'active'));
        } else {
            tickIcon.style.display = 'inline-flex';
            lineHeightSpans.forEach(span => span.classList.remove(prefix + 'active'));
            lineHeightSpans.forEach((span, index) => {
                if (index <= lineHeightCount - 1) {
                    span.classList.add(prefix + 'active');
                }
            });
            lineHeightCheck.classList.add(prefix + 'featureSteps-visible');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const lineHeightBtn = document.getElementById('btn-s12');
        if (lineHeightBtn) {
            lineHeightBtn.addEventListener('click', adjustLineHeight)
        }
    });

    // Add this function to create/update CSS classes dynamically for text spacing
    function createTextSpacingStyles() {
        // Remove existing text spacing styles if they exist
        const existingStyle = document.getElementById('ux4g-text-spacing-styles');
        if (existingStyle) {
            existingStyle.remove();
        }

        // Create new style element
        const style = document.createElement('style');
        style.id = 'ux4g-text-spacing-styles';

        // Create dynamic CSS class based on current textSpacingCount
        if (textSpacingCount > 0) {
            style.textContent = `
                .ux4g-text-spacing {
                    letter-spacing: ${textSpacingCount * 0.12}em !important;
                    word-spacing: ${textSpacingCount * 0.16}em !important;
                }
            `;
        }

        document.head.appendChild(style);
    }

    // Updated updateLetterSpacing function to use CSS classes
    function updateLetterSpacing() {
        // Remove existing text spacing classes from all elements
        document.querySelectorAll('.ux4g-text-spacing').forEach((el) => {
            el.classList.remove('ux4g-text-spacing');
        });

        if (textSpacingCount > 0) {
            // Create/update CSS class
            createTextSpacingStyles();

            // Add class to elements
            document.querySelectorAll('body *:not(.uwaw *):not(.uwaw)').forEach((el) => {
                el.classList.add('ux4g-text-spacing');
            });
        } else {
            // Remove the style element if textSpacingCount is 0
            const existingStyle = document.getElementById('ux4g-text-spacing-styles');
            if (existingStyle) {
                existingStyle.remove();
            }
        }
    }

    function adjustTextSpacing() {
        const button = document.getElementById('featureItem-ts');
        button.classList.add(prefix + 'feature-active');
        textSpacingCount = (textSpacingCount + 1) % 4;

        if (textSpacingCount > 0) {
            updateLetterSpacing();
        }

        saveSettings();
        const tickIcon = document.getElementById('tickIcon-ts');
        const textSpacingCheck = document.getElementById('featureSteps-ts');

        if (textSpacingCount <= 0) {
            button.classList.remove(prefix + 'feature-active');
            tickIcon.style.display = 'none';
            textSpacingCheck.classList.remove(prefix + 'featureSteps-visible');

            // Remove classes instead of inline styles
            document.querySelectorAll('.ux4g-text-spacing').forEach(el => {
                el.classList.remove('ux4g-text-spacing');
            });

            // Remove the style element
            const existingStyle = document.getElementById('ux4g-text-spacing-styles');
            if (existingStyle) {
                existingStyle.remove();
            }

            textSpacingSpans.forEach(span => span.classList.remove(prefix + 'active'))
        } else {
            tickIcon.style.display = 'inline-flex';
            textSpacingSpans.forEach(span => span.classList.remove(prefix + 'active'));
            textSpacingSpans.forEach((span, index) => {
                if (index <= textSpacingCount - 1) {
                    span.classList.add(prefix + 'active')
                }
            });
            textSpacingCheck.classList.add(prefix + 'featureSteps-visible')
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const spacingBtn = document.getElementById('btn-s13');
        if (spacingBtn) {
            spacingBtn.addEventListener('click', adjustTextSpacing)
        }
    });

    function toggleHighlightLinks() {
        const button = document.getElementById('featureItem-ht');
        const tickIcon = document.getElementById('tickIcon-ht');
        button.classList.toggle(prefix + 'feature-active');
        tickIcon.style.display = tickIcon.style.display === 'inline-flex' ? 'none' : 'inline-flex';
        document.body.classList.toggle(prefix + 'highlight-links');
        let tool = document.querySelector('.uwaw');
        if (tool) {
            tool.classList.remove(prefix + 'highlight-links')
        }
        saveSettings()
    }
    document.addEventListener('DOMContentLoaded', function () {
        const highlightBtn = document.getElementById('btn-s10');
        if (highlightBtn) {
            highlightBtn.addEventListener('click', toggleHighlightLinks)
        }
    });

    function toggleDyslexiaMode() {
        const button = document.getElementById('featureItem-df');
        const tickIcon = document.getElementById('tickIcon-df');
        button.classList.toggle(prefix + 'feature-active');
        tickIcon.style.display = tickIcon.style.display === 'inline-flex' ? 'none' : 'inline-flex';
        document.body.classList.toggle(prefix + 'dyslexia-mode');
        saveSettings()
    }
    document.addEventListener('DOMContentLoaded', function () {
        const dyslexiaBtn = document.getElementById('btn-df');
        if (dyslexiaBtn) {
            dyslexiaBtn.addEventListener('click', toggleDyslexiaMode)
        }
    });

    function hideImages() {
        const button = document.getElementById('featureItem-hi');
        const tickIcon = document.getElementById('tickIcon-hi');
        button.classList.toggle(prefix + 'feature-active');
        tickIcon.style.display = tickIcon.style.display === 'inline-flex' ? 'none' : 'inline-flex';
        document.body.classList.toggle(prefix + 'hide-images');
        saveSettings()
    }
    document.addEventListener('DOMContentLoaded', function () {
        const hideImagesBtn = document.getElementById('btn-s11');
        if (hideImagesBtn) {
            hideImagesBtn.addEventListener('click', hideImages)
        }
    });

    function changeCursor() {
        const button = document.getElementById('featureItem-Cursor');
        const tickIcon = document.getElementById('tickIcon-cursor');
        button.classList.toggle(prefix + 'feature-active');
        tickIcon.style.display = tickIcon.style.display === 'inline-flex' ? 'none' : 'inline-flex';
        document.body.classList.toggle(prefix + 'custom-cursor');
        saveSettings()
    }
    document.addEventListener('DOMContentLoaded', function () {
        const cursorBtn = document.getElementById('btn-cursor');
        if (cursorBtn) {
            cursorBtn.addEventListener('click', changeCursor)
        }
    });

    function toggleDarkMode() {
        const button = document.getElementById('featureItem-ht-dark');
        const tickIcon = document.getElementById('tickIcon-ht-dark');
        button.classList.toggle(prefix + 'feature-active');
        tickIcon.style.display = tickIcon.style.display === 'inline-flex' ? 'none' : 'inline-flex';
        document.body.classList.toggle(prefix + "dark-mode");
        saveSettings()
    }
    document.addEventListener('DOMContentLoaded', function () {
        const darkModeBtn = document.getElementById('dark-btn');
        if (darkModeBtn) {
            darkModeBtn.addEventListener('click', toggleDarkMode)
        }
    });
    // on scape key press, close the panel
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            const uwMain = document.getElementById('uw-main');
            if (uwMain) {
                uwMain.style.right = '-530px';
            }
        }
    });

    function invertColor() {
        const button = document.getElementById('featureItem-ic');
        const tickIcon = document.getElementById('tickIcon-ic');
        button.classList.toggle(prefix + 'feature-active');
        tickIcon.style.display = tickIcon.style.display === 'inline-flex' ? 'none' : 'inline-flex';
        document.documentElement.classList.toggle(prefix + "invert-colors");
        saveSettings()
    }
    document.addEventListener('DOMContentLoaded', function () {
        const invertBtn = document.getElementById('btn-invert');
        if (invertBtn) {
            invertBtn.addEventListener('click', invertColor)
        }
    });

    function saturateColor() {
        const button = document.getElementById('featureItem-saturate');
        const tickIcon = document.getElementById('tickIcon-saturate');
        const saturateCheck = document.getElementById('featureStepsSaturate');

        // Remove existing saturate classes
        document.documentElement.classList.remove(prefix + 'saturate-low', prefix + 'saturate-high', prefix + 'saturate-desaturate');

        // Increment saturate count and cycle through options (0-3)
        saturateCount = (saturateCount + 1) % 4;

        // Hide all saturate text labels
        for (let i = 0; i <= 3; i++) {
            const textElement = document.getElementById(`saturate-text-${i}`);
            const textElement1 = document.getElementById(`saturate-detail-text-${i}`);
            if (textElement) {
                textElement.style.display = 'none';
            }
            if (textElement1) {
                textElement1.style.display = 'none';
            }
        }

        // Show current saturate text and apply corresponding class
        const currentTextElement = document.getElementById(`saturate-text-${saturateCount}`);
        if (currentTextElement) {
            currentTextElement.style.display = 'inline';
        }

        const currentTextElement1 = document.getElementById(`saturate-detail-text-${saturateCount}`);
        if (currentTextElement1) {
            currentTextElement1.style.display = 'inline';
        }

        if (saturateCount === 0) {
            // Reset state - no saturate applied
            button.classList.remove(prefix + 'feature-active');
            tickIcon.style.display = 'none';
            saturateCheck.classList.remove(prefix + 'featureSteps-visible');
            saturateSpans.forEach(span => span.classList.remove(prefix + 'active'));
        } else {
            // Apply saturate based on count
            button.classList.add(prefix + 'feature-active');
            tickIcon.style.display = 'inline-flex';
            saturateCheck.classList.add(prefix + 'featureSteps-visible');

            // Apply the appropriate saturate class
            if (saturateCount === 1) {
                document.documentElement.classList.add(prefix + 'saturate-low');
            } else if (saturateCount === 2) {
                document.documentElement.classList.add(prefix + 'saturate-high');
            } else if (saturateCount === 3) {
                document.documentElement.classList.add(prefix + 'saturate-desaturate');
            }

            // Update step indicators
            saturateSpans.forEach(span => span.classList.remove(prefix + 'active'));
            saturateSpans.forEach((span, index) => {
                if (index <= saturateCount - 1) {
                    span.classList.add(prefix + 'active');
                }
            });
        }

        saveSettings();
    }

    document.addEventListener('DOMContentLoaded', function () {
        const saturateBtn = document.getElementById('btn-saturate');
        if (saturateBtn) {
            saturateBtn.addEventListener('click', saturateColor)
        }
    });

    function pauseAnimation() {
        const button = document.getElementById('featureItem-animate');
        const tickIcon = document.getElementById('tickIcon-animate');
        button.classList.toggle(prefix + 'feature-active');
        tickIcon.style.display = tickIcon.style.display === 'inline-flex' ? 'none' : 'inline-flex';

        // Toggle pause animation class on body
        document.body.classList.toggle(prefix + 'animation-pause');

        // Pause/resume videos and audio elements
        const isAnimationPaused = document.body.classList.contains(prefix + 'animation-pause');

        if (isAnimationPaused) {
            // Pause all videos
            document.querySelectorAll('video:not(.uwaw video):not(.uwaw)').forEach(video => {
                if (!video.paused) {
                    video.setAttribute('data-was-playing', 'true');
                    video.pause();
                }
            });

            // Pause all audio elements
            document.querySelectorAll('audio:not(.uwaw audio):not(.uwaw)').forEach(audio => {
                if (!audio.paused) {
                    audio.setAttribute('data-was-playing', 'true');
                    audio.pause();
                }
            });
        } else {
            // Resume all videos that were playing
            document.querySelectorAll('video[data-was-playing="true"]:not(.uwaw video):not(.uwaw)').forEach(video => {
                video.play();
                video.removeAttribute('data-was-playing');
            });

            // Resume all audio elements that were playing
            document.querySelectorAll('audio[data-was-playing="true"]:not(.uwaw audio):not(.uwaw)').forEach(audio => {
                audio.play();
                audio.removeAttribute('data-was-playing');
            });
        }

        saveSettings();
    }

    document.addEventListener('DOMContentLoaded', function () {
        const pauseAnimationBtn = document.getElementById('btn-animation');
        if (pauseAnimationBtn) {
            pauseAnimationBtn.addEventListener('click', pauseAnimation);
        }
    });

    function toggleADHDFriendlyMode() {
        const button = document.getElementById('featureItem-adhd');
        const tickIcon = document.getElementById('tickIcon-adhd');

        if (adhdActive) {
            // Remove active state
            button.classList.remove(prefix + 'feature-active');
            tickIcon.style.display = 'none';

            readingMask.classList.add('mask-hidden');
            readingMask.classList.remove('mask-visible');
            document.body.classList.remove(prefix + 'adhd-saturate');
            adhdActive = false;
        } else {
            // Add active state
            button.classList.add(prefix + 'feature-active');
            tickIcon.style.display = 'inline-flex';
            readingMask.classList.remove('mask-hidden');
            readingMask.classList.add('mask-visible');
            document.body.classList.add(prefix + 'adhd-saturate');
            adhdActive = true;
        }

        saveSettings();
    }

    document.addEventListener('DOMContentLoaded', function () {
        const adhdBtn = document.getElementById('btn-adhd');
        if (adhdBtn) {
            adhdBtn.addEventListener('click', toggleADHDFriendlyMode)
        }
    });

    function resetSettings() {
        const cookieDomain = getCookieDomain();

        if (cookieDomain) {
            document.cookie = `${SETTINGS_KEY}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=${cookieDomain}`;
        } else {
            document.cookie = `${SETTINGS_KEY}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/`;
        }
        speechSynthesisInstance.cancel();
        const allTextNodes = document.body.querySelectorAll('p:not(.uwaw *), h1:not(.uwaw *), h2:not(.uwaw *), h3:not(.uwaw *), h4:not(.uwaw *), h5:not(.uwaw *), h6:not(.uwaw *), span:not(.uwaw *), li:not(.uwaw *), a:not(.uwaw *)');
        allTextNodes.forEach(node => {
            node.innerHTML = node.innerHTML.replace(/<span style="font-weight: bold !important;?[^"']*["']>(.*?)<\/span>/g, '$1')
        });


        // Remove line height classes and styles
        document.querySelectorAll('.ux4g-line-height').forEach((el) => {
            el.classList.remove('ux4g-line-height');
        });

        const lineHeightStyle = document.getElementById('ux4g-line-height-styles');
        if (lineHeightStyle) {
            lineHeightStyle.remove();
        }

        // Remove text spacing classes and styles
        document.querySelectorAll('.ux4g-text-spacing').forEach((el) => {
            el.classList.remove('ux4g-text-spacing');
        });

        const textSpacingStyle = document.getElementById('ux4g-text-spacing-styles');
        if (textSpacingStyle) {
            textSpacingStyle.remove();
        }

        document.querySelectorAll('body *:not(.uwaw *):not(.uwaw)').forEach((el) => {
            el.style.fontSize = "";
            el.style.lineHeight = "";
            el.style.cursor = "";
            el.style.zoom = "1";
            el.classList.remove(prefix + "active");
            el.classList.remove(prefix + 'animation-pause');
        });

        adhdActive = false;
        document.body.classList.remove(prefix + "dark-mode", prefix + "custom-cursor", prefix + "dyslexia-mode", prefix + "highlight-links", prefix + "hide-images", prefix + "animation-pause", prefix + 'saturate-low', prefix + 'saturate-high', prefix + 'saturate-desaturate', prefix + 'adhd-saturate');
        // Resume any paused media
        document.querySelectorAll('video[data-was-playing="true"]:not(.uwaw video):not(.uwaw)').forEach(video => {
            video.play();
            video.removeAttribute('data-was-playing');
        });

        document.querySelectorAll('audio[data-was-playing="true"]:not(.uwaw audio):not(.uwaw)').forEach(audio => {
            audio.play();
            audio.removeAttribute('data-was-playing');
        });
        document.documentElement.classList.remove(prefix + "invert-colors");
        fontSizeCount = 0;
        lineHeightCount = 0;
        textSpacingCount = 0;
        saturateCount = 0;
        screenReader = !1;
        saveSettings();
        loadSettings();
        const checkboxes = document.querySelectorAll('.uwaw input[type="checkbox"]');
        checkboxes.forEach(checkbox => checkbox.checked = !1);
        document.querySelectorAll('.font-size-visible, .line-height-visible, .text-spacing-visible').forEach(el => el.classList.remove(prefix + 'span-visible'))
        readingMask.classList.add('mask-hidden');
        readingMask.classList.remove('mask-visible');
    }

    document.addEventListener('DOMContentLoaded', function () {
        const resetBtn = document.getElementById('reset-all');
        if (resetBtn) {
            resetBtn.addEventListener('click', resetSettings)
        }
    });

    function getCookie(name) {
        const value = "; " + document.cookie;
        const parts = value.split("; " + name + "=");
        if (parts.length === 2) return decodeURIComponent(parts.pop().split(";").shift());
    }

    function saveSettings() {
        const settings = {
            screenReader: screenReader,
            fontSizeCount: fontSizeCount - 1,
            lineHeightCount: lineHeightCount - 1,
            textSpacingCount: textSpacingCount - 1,
            saturateCount: saturateCount,
            highlightLinks: document.body.classList.contains(prefix + 'highlight-links'),
            dyslexiaMode: document.body.classList.contains(prefix + 'dyslexia-mode'),
            hideImages: document.body.classList.contains(prefix + 'hide-images'),
            darkMode: document.body.classList.contains(prefix + 'dark-mode'),
            cursorChanged: document.body.classList.contains(prefix + 'custom-cursor'),
            invert: document.documentElement.classList.contains(prefix + 'invert-colors'),
            adhdFriendly: document.body.classList.contains(prefix + 'adhd-saturate'),
            pauseAnimation: document.body.classList.contains(prefix + 'animation-pause'),
        };

        const jsonStr = JSON.stringify(settings);
        const expiryDate = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toUTCString(); // 30 days

        const cookieDomain = getCookieDomain();
        // console.log(cookieDomain);

        // Check if running on HTTPS
        if (location.protocol === 'https:') {
            if (cookieDomain) {
                // For production domains with HTTPS
                document.cookie = `accessibilitySettings=${encodeURIComponent(jsonStr)}; expires=${expiryDate}; path=/; domain=${cookieDomain}; SameSite=Strict; Secure`;
            } else {
                // For localhost/IP with HTTPS
                document.cookie = `accessibilitySettings=${encodeURIComponent(jsonStr)}; expires=${expiryDate}; path=/; SameSite=Strict; Secure`;
            }
        } else {
            // Fallback for HTTP (development)
            if (cookieDomain) {
                document.cookie = `accessibilitySettings=${encodeURIComponent(jsonStr)}; expires=${expiryDate}; path=/; domain=${cookieDomain}`;
            } else {
                document.cookie = `accessibilitySettings=${encodeURIComponent(jsonStr)}; expires=${expiryDate}; path=/`;
            }
            // console.warn("⚠️ Secure cookie attribute skipped (not HTTPS)");
        }

        // console.log("✅ Cookie saved for domain:", cookieDomain || 'current domain');

    }

    function updateWidgetToggles(settings) {
        const speakOn = document.getElementById('tickIcon_sp');
        if (speakOn) {
            speakOn.style.display = settings.screenReader ? 'inline-flex' : 'none'
        }
        const highlightToggle = document.getElementById('tickIcon-ht');
        if (highlightToggle) {
            highlightToggle.style.display = settings.highlightLinks ? 'inline-flex' : 'none'
        }
        const darkModeToggle = document.getElementById('tickIcon-ht-dark');
        if (darkModeToggle) {
            darkModeToggle.style.display = settings.darkMode ? 'inline-flex' : 'none'
        }
        const invertToggle = document.getElementById('tickIcon-ic');
        if (invertToggle) {
            invertToggle.style.display = settings.invert ? 'inline-flex' : 'none'
        }
        const dyslexiaToggle = document.getElementById('tickIcon-df');
        if (dyslexiaToggle) {
            dyslexiaToggle.style.display = settings.dyslexiaMode ? 'inline-flex' : 'none'
        }
        const adhdToggle = document.getElementById('tickIcon-adhd');
        if (adhdToggle) {
            adhdToggle.style.display = settings.adhdFriendly ? 'inline-flex' : 'none'
        }
        const hideImagesToggle = document.getElementById('tickIcon-hi');
        if (hideImagesToggle) {
            hideImagesToggle.style.display = settings.hideImages ? 'inline-flex' : 'none'
        }
        const cursorToggle = document.getElementById('tickIcon-cursor');
        if (cursorToggle) {
            cursorToggle.style.display = settings.cursorChanged ? 'inline-flex' : 'none'
        }
        const saturateToggle = document.getElementById('tickIcon-saturate');
        if (saturateToggle) {
            saturateToggle.style.display = settings.saturateCount > 0 ? 'inline-flex' : 'none'
        }

        const animationToggle = document.getElementById('tickIcon-animate');
        if (animationToggle) {
            animationToggle.style.display = settings.pauseAnimation ? 'inline-flex' : 'none'
        }

        adjustFontSize('+1');
        adjustLineHeight();
        adjustTextSpacing()
    }

    function loadSettings() {
        let settings = getCookie(SETTINGS_KEY);
        if (!settings) {
            settings = getCookie(SETTINGS_KEY);
        }
        // console.log(settings);
        if (settings) {
            settings = JSON.parse(settings);
            fontSizeCount = settings.fontSizeCount || 0;
            lineHeightCount = settings.lineHeightCount || 0;
            textSpacingCount = settings.textSpacingCount || 0;
            saturateCount = settings.saturateCount || 0;
            if (settings.screenReader) {
                screenReader = settings.screenReader;
                const button = document.getElementById('featureItem_sp');
                button.classList.add(prefix + 'feature-active')
            } else {
                const button = document.getElementById('featureItem_sp');
                button.classList.remove(prefix + 'feature-active')
            }
            if (settings.highlightLinks) {
                document.body.classList.add(prefix + 'highlight-links');
                const button = document.getElementById('featureItem-ht');
                button.classList.add(prefix + 'feature-active')
            } else {
                const button = document.getElementById('featureItem-ht');
                button.classList.remove(prefix + 'feature-active')
            }
            if (settings.dyslexiaMode) {
                document.body.classList.add(prefix + 'dyslexia-mode');
                const button = document.getElementById('featureItem-df');
                button.classList.add(prefix + 'feature-active')
            } else {
                const button = document.getElementById('featureItem-df');
                button.classList.remove(prefix + 'feature-active')
            }
            if (settings.hideImages) {
                document.body.classList.add(prefix + 'hide-images');
                const button = document.getElementById('featureItem-hi');
                button.classList.add(prefix + 'feature-active')
            } else {
                const button = document.getElementById('featureItem-hi');
                button.classList.remove(prefix + 'feature-active')
            }
            if (settings.darkMode) {
                document.body.classList.add(prefix + 'dark-mode');
                const button = document.getElementById('featureItem-ht-dark');
                button.classList.add(prefix + 'feature-active')
            } else {
                const button = document.getElementById('featureItem-ht-dark');
                button.classList.remove(prefix + 'feature-active')
            }
            if (settings.invert) {
                document.documentElement.classList.toggle(prefix + "invert-colors");
                const button = document.getElementById('featureItem-ic');
                button.classList.add(prefix + 'feature-active')
            } else {
                const button = document.getElementById('featureItem-ic');
                button.classList.remove(prefix + 'feature-active')
            }
            if (settings.cursorChanged) {
                document.body.classList.add(prefix + 'custom-cursor');
                const button = document.getElementById('featureItem-Cursor');
                button.classList.add(prefix + 'feature-active')
            } else {
                const button = document.getElementById('featureItem-Cursor');
                button.classList.remove(prefix + 'feature-active')
            }
            if (settings.adhdFriendly) {
                toggleADHDFriendlyMode();
                readingMask.classList.remove('mask-hidden');
                const button = document.getElementById('featureItem-adhd');
                button.classList.add(prefix + 'feature-active');
            }
            else {
                readingMask.classList.add('mask-hidden');
                const button = document.getElementById('featureItem-adhd');
                button.classList.remove(prefix + 'feature-active');
            }

            applyTextSettings()

            // Apply line height settings using classes
            if (lineHeightCount > 0) {
                updateLineHeight();
            }

            // Apply text spacing settings using classes  
            if (textSpacingCount > 0) {
                updateLetterSpacing();
            }

            // Load saturate settings
            if (saturateCount > 0) {
                const button = document.getElementById('featureItem-saturate');
                button.classList.add(prefix + 'feature-active');

                // Apply saved saturate class
                if (saturateCount === 1) {
                    document.documentElement.classList.add(prefix + 'saturate-low');
                } else if (saturateCount === 2) {
                    document.documentElement.classList.add(prefix + 'saturate-high');
                } else if (saturateCount === 3) {
                    document.documentElement.classList.add(prefix + 'saturate-desaturate');
                }

                // Show correct text and icons
                for (let i = 0; i <= 3; i++) {
                    const textElement = document.getElementById(`saturate-text-${i}`);
                    const detailTextElement = document.getElementById(`saturate-detail-text-${i}`);
                    if (textElement) {
                        textElement.style.display = i === saturateCount ? 'inline' : 'none';
                    }
                    if (detailTextElement) {
                        detailTextElement.style.display = i === saturateCount ? 'inline' : 'none';
                    }
                }

                const saturateCheck = document.getElementById('featureStepsSaturate');
                saturateCheck.classList.add(prefix + 'featureSteps-visible');
                saturateSpans.forEach((span, index) => {
                    if (index <= saturateCount - 1) {
                        span.classList.add(prefix + 'active');
                    }
                });
            }
            else {
                const button = document.getElementById('featureItem-saturate');
                button.classList.remove(prefix + 'feature-active');
                document.documentElement.classList.remove(prefix + 'saturate-low');
                document.documentElement.classList.remove(prefix + 'saturate-high');
                document.documentElement.classList.remove(prefix + 'saturate-desaturate');
                const saturateCheck = document.getElementById('featureStepsSaturate');
                saturateCheck.classList.remove(prefix + 'featureSteps-visible');
                saturateSpans.forEach((span, index) => {
                    if (index <= saturateCount - 1) {
                        span.classList.remove(prefix + 'active');
                    }
                });

            }

            if (settings.pauseAnimation) {
                // console.log('Animation pause is enabled');

                const button = document.getElementById('featureItem-animate');
                const tickIcon = document.getElementById('tickIcon-animate');

                if (button) button.classList.add(prefix + 'feature-active');
                if (tickIcon) tickIcon.style.display = 'inline-flex';

                document.body.classList.add(prefix + 'animation-pause');

                // 🔁 Delay pausing slightly to allow media to finish initializing
                setTimeout(() => {
                    document.querySelectorAll('video:not(.uwaw video):not(.uwaw)').forEach(video => {
                        video.setAttribute('data-was-playing', 'true');
                        video.pause();
                    });

                    document.querySelectorAll('audio:not(.uwaw audio):not(.uwaw)').forEach(audio => {
                        audio.setAttribute('data-was-playing', 'true');
                        audio.pause();
                    });
                }, 300); // Adjust delay if needed
            } else {
                const button = document.getElementById('featureItem-animate');
                const tickIcon = document.getElementById('tickIcon-animate');

                if (button) button.classList.remove(prefix + 'feature-active');
                if (tickIcon) tickIcon.style.display = 'none';
            }
            updateWidgetToggles(settings)
        }
    }

    const modal = document.getElementById("uw-main");

    // Toggle modal on Ctrl + F2
    document.addEventListener("keydown", function (e) {
        if (e.ctrlKey && e.key === "F2") {
            e.preventDefault();
            // Toggle based on current right style
            const currentRight = window.getComputedStyle(modal).right;
            if (currentRight === "0px") {
                // Close
                modal.style.right = "-530px"; // adjust width as needed
            } else {
                // Open
                modal.style.right = "0";
            }
        }
    });

    function detectRouteChange() {
        setInterval(() => {
            let currentPath = window.location.pathname;
            if (currentPath !== lastPath) {
                // console.log('Route changed from', lastPath, 'to', currentPath);
                speechSynthesisInstance.cancel();
                lastPath = currentPath;

                // Get settings from cookie (not localStorage)
                const settingsStr = getCookie(SETTINGS_KEY);
                if (settingsStr) {
                    const settings = JSON.parse(settingsStr);

                    // Re-apply ADHD mode if it was active
                    if (settings.adhdFriendly && !adhdActive) {
                        toggleADHDFriendlyMode()
                    }

                    // Apply line height changes using classes
                    if (settings.lineHeightCount && settings.lineHeightCount > 0) {
                        lineHeightCount = settings.lineHeightCount + 1;
                        updateLineHeight();
                    }

                    // Apply text spacing changes using classes
                    if (settings.textSpacingCount && settings.textSpacingCount > 0) {
                        textSpacingCount = settings.textSpacingCount + 1;
                        updateLetterSpacing();
                    }
                }
            }
        }, 1000);
    }
})()


//this code for page loading showing accessibility code issue fixed 
window.addEventListener("load", function () {
    document.body.classList.remove("accessibility-loading");
});

// // start here hide image and background hide
function hideBackgroundImages() {
    if (!document.body.classList.contains('ux4g-accessibility-hide-images')) return;

    document.querySelectorAll('*:not(#uw-main):not(#uw-main *):not(.reading-mask-horizontal)')
        .forEach(el => {
            const bgImage = getComputedStyle(el).backgroundImage;
            if (bgImage.includes('url(')) {
                el.dataset.origBgImage = bgImage; // Store original
                el.style.setProperty('background-image', 'none', 'important');
            }
        });
}

function restoreBackgroundImages() {
    document.querySelectorAll('[data-orig-bg-image]').forEach(el => {
        el.style.backgroundImage = el.dataset.origBgImage;
        delete el.dataset.origBgImage;
    });
}

function toggleBackgroundImageHiding() {
    if (document.body.classList.contains('ux4g-accessibility-hide-images')) {
        hideBackgroundImages();
    } else {
        restoreBackgroundImages();
    }
}

document.addEventListener('DOMContentLoaded', hideBackgroundImages);

new MutationObserver(mutations => {
    if (mutations.some(m => m.attributeName === 'class')) {
        toggleBackgroundImageHiding();
    }
}).observe(document.body, { attributes: true });

// end here hide image and background hide
