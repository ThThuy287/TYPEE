/* global jQuery, ajaxurl, wdp_ai_chat_data */
(function ($) {
    'use strict';

    var STORAGE_KEY = 'wdp_ai_chat_ui_state';
    var cfg = window.wdp_ai_chat_data;

    function escapeHtml(text) {
        return $('<div>').text(text).html();
    }

    // A message is plain text, except for links to our own documentation and support (the
    // "unable to create the rule" reply has them), to plugins on wordpress.org and to the
    // Connectors page: everything is escaped, then only such <a href="..."> tags are turned
    // back into links.
    function messageHtml(text) {
        return escapeHtml(text).replace(/&lt;a href="([^"\s<>]*)"&gt;([^<]*?)&lt;\/a&gt;/g, function (tag, url, label) {
            var allowed = /^https:\/\/(?:docs\.algolplus\.com\/|algolplus\.freshdesk\.com\/|wordpress\.org\/plugins\/)/.test(url)
                || (cfg.connectors_url && url === escapeHtml(cfg.connectors_url));

            return allowed ? '<a href="' + url + '" target="_blank" rel="noopener noreferrer">' + label + '</a>' : tag;
        });
    }

    function loadUiState() {
        try {
            var raw = window.localStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : {};
        } catch (e) {
            return {};
        }
    }

    function saveUiState(state) {
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (e) {
            // storage unavailable (private mode, disabled, quota) - persistence is a nice-to-have, not fatal
        }
    }

    function shouldAutoOpenFromUrl() {
        try {
            return /[?&]wdp_open_ai_chat=1\b/.test(window.location.search);
        } catch (e) {
            return false;
        }
    }

    // Drops the one-shot wdp_open_ai_chat url param after it's been acted on, so a page
    // refresh or back/forward navigation doesn't keep re-opening the panel on its own.
    function stripAutoOpenParam() {
        if (!window.history || !window.history.replaceState) {
            return;
        }
        try {
            var url = new URL(window.location.href);
            url.searchParams.delete('wdp_open_ai_chat');
            window.history.replaceState(null, '', url.toString());
        } catch (e) {
            // URL() unsupported or blocked - leaving the param in place is harmless
        }
    }

    function post(method, data, onSuccess, onError) {
        var params = $.extend({
            action: 'wdp_ajax',
            method: method,
        }, data || {});
        params[cfg.security_query_arg] = cfg.security;

        $.post(ajaxurl, params, function (response) {
            if (!response || !response.success) {
                if (onError) {
                    onError(response && response.data);
                }
                return;
            }
            onSuccess(response.data);
        }, 'json').fail(function () {
            if (onError) {
                onError(null);
            }
        });
    }

    var AiChat = {
        $root: null,
        $toggle: null,
        $panel: null,
        $messages: null,
        $input: null,
        $send: null,
        $context: null,
        $contextLabel: null,
        $maximize: null,
        $compose: null,
        opened: false,
        maximized: false,
        historyLoaded: false,
        currentRule: null,
        lastOpenedEl: null,
        dismissedEl: null,

        init: function () {
            this.$root = $(this.markup()).appendTo('body');
            this.$toggle = this.$root.find('.wdp-ai-chat-toggle');
            this.$panel = this.$root.find('.wdp-ai-chat-panel');
            this.$messages = this.$root.find('.wdp-ai-chat-messages');
            this.$input = this.$root.find('.wdp-ai-chat-input');
            this.$send = this.$root.find('.wdp-ai-chat-send');
            this.$context = this.$root.find('.wdp-ai-chat-context');
            this.$contextLabel = this.$root.find('.wdp-ai-chat-context-label');
            this.$maximize = this.$root.find('.wdp-ai-chat-maximize');
            this.$compose = this.$root.find('.wdp-ai-chat-compose');

            this.bindEvents();
            this.bindResizeHandles();
            this.watchRuleContext();
            this.applyAvailability();
            this.restoreUiState();

            if (shouldAutoOpenFromUrl()) {
                this.open();
                stripAutoOpenParam();
            }
        },

        // cfg.status (RuleAssistant::checkAvailability()) is a cheap, no-API-call
        // check of whether the chat can be used at all - shown up front (in place of the
        // usual greeting/history) instead of only surfacing as a reply after the admin has
        // already typed and sent a message.
        applyAvailability: function () {
            var status = cfg.status || {};
            var message = '';

            if (!status.wp_version_ok) {
                message = cfg.i18n.wp_version_required;
            } else if (!status.ai_configured) {
                message = cfg.i18n.not_configured;
            }

            if (!message) {
                return;
            }

            this.$compose.prop('hidden', true);
            // shown as a message from the assistant, so it reads like the rest of the chat
            this.$messages.empty();
            this.appendMessage('assistant', message);
            // keeps loadHistory() (called from open() when !historyLoaded) from overwriting
            // this message with the real conversation history/greeting
            this.historyLoaded = true;
        },

        markup: function () {
            return '' +
                '<div class="wdp-ai-chat">' +
                '  <button type="button" class="wdp-ai-chat-toggle" title="' + escapeHtml(cfg.i18n.title) + '" aria-label="' + escapeHtml(cfg.i18n.title) + '">' +
                '    <span class="dashicons dashicons-format-chat"></span>' +
                '  </button>' +
                '  <div class="wdp-ai-chat-panel" hidden>' +
                '    <div class="wdp-ai-chat-resize wdp-ai-chat-resize-w" data-axis="w"></div>' +
                '    <div class="wdp-ai-chat-resize wdp-ai-chat-resize-h" data-axis="h"></div>' +
                '    <div class="wdp-ai-chat-header">' +
                '      <span>' + escapeHtml(cfg.i18n.title) + '</span>' +
                '      <button type="button" class="wdp-ai-chat-maximize" title="' + escapeHtml(cfg.i18n.maximize) + '" aria-label="' + escapeHtml(cfg.i18n.maximize) + '">' +
                '        <span class="dashicons dashicons-editor-expand"></span>' +
                '      </button>' +
                '      <button type="button" class="wdp-ai-chat-minimize" title="' + escapeHtml(cfg.i18n.minimize) + '" aria-label="' + escapeHtml(cfg.i18n.minimize) + '">' +
                '        <span class="dashicons dashicons-no-alt"></span>' +
                '      </button>' +
                '    </div>' +
                '    <div class="wdp-ai-chat-messages"></div>' +
                '    <div class="wdp-ai-chat-compose">' +
                '      <textarea class="wdp-ai-chat-input" rows="2" placeholder="' + escapeHtml(cfg.i18n.placeholder) + '"></textarea>' +
                '      <div class="wdp-ai-chat-compose-footer">' +
                '        <span class="wdp-ai-chat-context" hidden>' +
                '          <span class="wdp-ai-chat-context-label"></span>' +
                '          <button type="button" class="wdp-ai-chat-context-remove" title="' + escapeHtml(cfg.i18n.remove_context) + '" aria-label="' + escapeHtml(cfg.i18n.remove_context) + '">&times;</button>' +
                '        </span>' +
                '        <button type="button" class="wdp-ai-chat-send" aria-label="' + escapeHtml(cfg.i18n.send) + '" disabled>' +
                '          <span class="dashicons dashicons-arrow-up-alt2"></span>' +
                '        </button>' +
                '      </div>' +
                '    </div>' +
                '  </div>' +
                '</div>';
        },

        bindEvents: function () {
            var self = this;

            this.$root.on('click', '.wdp-ai-chat-toggle', function () {
                self.open();
            });

            // the nav-tab entry point lives in the shared admin tab bar (admin_page.php),
            // outside this.$root - it toggles the panel in place on whichever tab is open
            // (its href is only a no-JS fallback)
            $(document).on('click', '.wdp-ai-chat-nav-tab', function (e) {
                e.preventDefault();
                if (self.opened) {
                    self.close();
                } else {
                    self.open();
                }
            });

            this.$root.on('click', '.wdp-ai-chat-minimize', function () {
                self.close();
            });

            this.$root.on('click', '.wdp-ai-chat-maximize', function () {
                self.toggleMaximize();
            });

            this.$root.on('click', '.wdp-ai-chat-context-remove', function () {
                self.dismissCurrentRule();
            });

            this.$root.on('click', '.wdp-ai-chat-send', function () {
                self.send();
            });

            this.$root.on('input', '.wdp-ai-chat-input', function () {
                self.$send.prop('disabled', !$.trim(self.$input.val()));
            });

            this.$root.on('keydown', '.wdp-ai-chat-input', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    self.send();
                }
            });
        },

        open: function () {
            this.$panel.prop('hidden', false);
            // the toggle button's own space (see .wdp-ai-chat-panel in ai-chat.css) is freed
            // up for the panel to use while it's open, since the toggle is hidden and there's
            // nothing else to click there anyway
            this.$toggle.prop('hidden', true);
            this.opened = true;
            this.updateMaximizedSpacer();
            this.persistUiState();

            if (!this.historyLoaded) {
                this.loadHistory();
            }
        },

        close: function () {
            this.$panel.prop('hidden', true);
            this.$toggle.prop('hidden', false);
            this.opened = false;
            this.updateMaximizedSpacer();
            this.persistUiState();
        },

        toggleMaximize: function () {
            this.setMaximized(!this.maximized);
            this.persistUiState();
        },

        // The maximized panel covers a large strip across the bottom of the viewport (see
        // .wdp-ai-chat-maximized in ai-chat.css) - while it's both open AND maximized, this
        // class adds matching bottom padding to the rules list so its last rows can still be
        // scrolled into view above the chat instead of staying hidden behind it.
        updateMaximizedSpacer: function () {
            $('body').toggleClass('wdp-ai-chat-maximized-active', this.opened && this.maximized);
        },

        setMaximized: function (maximized) {
            this.maximized = maximized;

            if (this.maximized) {
                // drop any manual (drag-resized) size so the maximized CSS size applies
                this.$panel.css({ width: '', height: '' });
                this.alignToPostboxContainer();
                $(window).on('resize.wdpAiChatAlign', $.proxy(this.alignToPostboxContainer, this));
            } else {
                $(window).off('resize.wdpAiChatAlign');
                // drop the inline left/width alignToPostboxContainer() set, so the CSS
                // default (or a manual drag-resize) applies again once un-maximized
                this.$panel.css({ left: '', width: '', right: '' });
            }

            this.$panel.toggleClass('wdp-ai-chat-maximized', this.maximized);
            this.updateMaximizedSpacer();
            this.$maximize
                .attr('title', this.maximized ? cfg.i18n.restore : cfg.i18n.maximize)
                .attr('aria-label', this.maximized ? cfg.i18n.restore : cfg.i18n.maximize)
                .find('.dashicons')
                .toggleClass('dashicons-editor-expand', !this.maximized)
                .toggleClass('dashicons-editor-contract', this.maximized);
        },

        // Lines the maximized panel's left edge and width up with the tab's content area
        // instead of a fixed viewport margin, so it reads as "the page, extended downward"
        // rather than an unrelated floating overlay: #postbox-container-2 (the box wrapping
        // the rules list) where the tab has one, otherwise .wdp_settings_container, which
        // every tab has and which sits at the same left/width - so the panel is the same
        // width on every tab. Read live via getBoundingClientRect() rather than mirrored in
        // CSS, since the actual position/width depends on the WP admin menu's state (folded,
        // responsive collapse, screen width) that plain CSS can't account for.
        alignToPostboxContainer: function () {
            var $container = $('#postbox-container-2');
            if (!$container.length) {
                $container = $('.wdp_settings_container').first();
            }
            if (!$container.length) {
                return;
            }

            var rect = $container[0].getBoundingClientRect();
            this.$panel.css({
                left: Math.round(rect.left) + 'px',
                width: Math.round(rect.width) + 'px',
                right: 'auto',
            });
        },

        persistUiState: function () {
            var rect = this.$panel[0].getBoundingClientRect();

            saveUiState({
                opened: this.opened,
                maximized: this.maximized,
                width: Math.round(rect.width),
                height: Math.round(rect.height),
            });
        },

        restoreUiState: function () {
            var state = loadUiState();

            if (!state.maximized && state.width) {
                this.$panel.css('width', state.width + 'px');
            }
            if (!state.maximized && state.height) {
                this.$panel.css('height', state.height + 'px');
            }
            if (state.maximized) {
                this.setMaximized(true);
            }
            if (state.opened) {
                this.open();
            }
        },

        bindResizeHandles: function () {
            var self = this;
            var axis = null;
            var startX, startY, startWidth, startHeight;
            var minWidth = 300;
            var minHeight = 320;

            this.$root.on('mousedown', '.wdp-ai-chat-resize', function (e) {
                if (self.maximized) {
                    return;
                }

                axis = $(this).data('axis');
                startX = e.clientX;
                startY = e.clientY;

                var rect = self.$panel[0].getBoundingClientRect();
                startWidth = rect.width;
                startHeight = rect.height;

                $('body').addClass('wdp-ai-chat-resizing').css('cursor', axis === 'w' ? 'ew-resize' : 'ns-resize');
                e.preventDefault();
            });

            $(document).on('mousemove.wdpAiChat', function (e) {
                if (!axis) {
                    return;
                }

                if (axis === 'w') {
                    var maxWidth = window.innerWidth - 48;
                    var newWidth = startWidth - (e.clientX - startX);
                    newWidth = Math.min(Math.max(newWidth, minWidth), maxWidth);
                    self.$panel.css('width', newWidth + 'px');
                } else {
                    // matches .wdp-ai-chat-panel's max-height in ai-chat.css - the toggle
                    // button is hidden while the panel is open, so there's nothing at the
                    // bottom of the viewport left to leave room for
                    var maxHeight = window.innerHeight - 76;
                    var newHeight = startHeight - (e.clientY - startY);
                    newHeight = Math.min(Math.max(newHeight, minHeight), maxHeight);
                    self.$panel.css('height', newHeight + 'px');
                }
            });

            $(document).on('mouseup.wdpAiChat', function () {
                if (axis) {
                    axis = null;
                    $('body').removeClass('wdp-ai-chat-resizing').css('cursor', '');
                    self.persistUiState();
                }
            });
        },

        // Tracks which rule's edit panel is currently expanded in the rules list,
        // so the chat can show what the conversation is about. Rule rows use the
        // WP postbox pattern (BaseVersion/assets/js/postbox.js): open === no "closed" class.
        //
        // postbox.js's handle_click() only toggles the CLICKED row's own "closed" class -
        // there's no accordion behavior here, so MULTIPLE rows can be open at once (opening
        // a new one does not close whatever was already open). Picking "the first open row"
        // would then keep pointing at whichever row happens to sort first in the list even
        // after the admin opens a different one further down - so instead, the last mutation
        // that REMOVED a row's "closed" class (i.e. the row most recently expanded) is
        // tracked (this.lastOpenedEl) and preferred; if that same row later gets closed
        // again, it's forgotten and the scan falls back to whatever's open.
        watchRuleContext: function () {
            var self = this;
            var $list = $('#rules-container');

            if (!$list.length) {
                return;
            }

            var update = function () {
                self.updateRuleContext();
            };

            update();

            var scheduled = null;
            new MutationObserver(function (mutations) {
                mutations.forEach(function (m) {
                    if (m.type !== 'attributes' || m.attributeName !== 'class') {
                        return;
                    }
                    var $target = $(m.target);
                    if (!$target.hasClass('wdp-ruleitem')) {
                        return;
                    }
                    if ($target.hasClass('closed')) {
                        if (self.lastOpenedEl === m.target) {
                            self.lastOpenedEl = null;
                        }
                    } else {
                        self.lastOpenedEl = m.target;
                        if (self.dismissedEl === m.target) {
                            // re-opened after being explicitly removed from context -
                            // that's a deliberate re-engagement, so bring it back
                            self.dismissedEl = null;
                        }
                    }
                });

                clearTimeout(scheduled);
                scheduled = setTimeout(update, 150);
            }).observe($list[0], { attributes: true, attributeFilter: ['class'], subtree: true });
        },

        // Removes the currently-tracked rule from the chat's context, as if it had never
        // been opened - the rule panel itself stays open on screen, only the chat stops
        // treating it as "the rule this conversation is about". Re-expanding that same row
        // (a deliberate re-engagement, handled in watchRuleContext's observer above) is the
        // only thing that un-dismisses it; otherwise it stays excluded even though it's
        // still technically "open" in the DOM.
        dismissCurrentRule: function () {
            if (this.lastOpenedEl) {
                this.dismissedEl = this.lastOpenedEl;
            } else if (this.currentRule && this.currentRule.$form && this.currentRule.$form.length) {
                this.dismissedEl = this.currentRule.$form[0];
            }

            this.updateRuleContext();
        },

        updateRuleContext: function () {
            var self = this;
            var preferEl = this.lastOpenedEl;
            var $open;

            if (preferEl && preferEl !== this.dismissedEl && $(preferEl).closest('#rules-container').length && !$(preferEl).hasClass('closed')) {
                $open = $(preferEl);
            } else {
                $open = $('#rules-container .wdp-ruleitem:not(.closed)').filter(function () {
                    return this !== self.dismissedEl;
                }).first();
            }

            if (!$open.length) {
                this.currentRule = null;
                this.$context.prop('hidden', true);
                this.$contextLabel.text('');
                return;
            }

            var id = $open.find('.rule-id').val();
            var title = $.trim($open.find('[data-wdp-title]').first().text());

            // $form is kept (not pre-serialized) so send() can serialize it fresh right
            // before posting - the admin may have tweaked fields in the open rule since it
            // was expanded, and the AI should reason about what's on screen right now, not
            // a stale snapshot from whenever this rule was first opened or last saved.
            this.currentRule = { id: id, title: title, $form: $open };

            var label = id ? ('#' + id) : cfg.i18n.new_rule;
            if (title) {
                label += ' ' + title;
            }

            this.$context.prop('hidden', false).attr('title', label);
            this.$contextLabel.text(label);
        },

        loadHistory: function () {
            var self = this;

            this.$messages.html('<div class="wdp-ai-chat-loading">' + escapeHtml(cfg.i18n.loading) + '</div>');

            post('ai_chat_history', {}, function (messages) {
                self.historyLoaded = true;
                self.$messages.empty();

                if (!messages || !messages.length) {
                    self.$messages.html('<div class="wdp-ai-chat-empty">' + escapeHtml(cfg.i18n.empty) + '</div>');
                    return;
                }

                messages.forEach(function (message) {
                    self.appendMessage(message.role, message.message);
                });
                self.scrollToBottom();
            }, function () {
                self.$messages.html('<div class="wdp-ai-chat-error">' + escapeHtml(cfg.i18n.error) + '</div>');
            });
        },

        appendMessage: function (role, text, extraClass) {
            var $el = $('<div class="wdp-ai-chat-message wdp-ai-chat-message-' + role + (extraClass ? ' ' + extraClass : '') + '"></div>');
            if (role === 'assistant') {
                $el.html(messageHtml(text));
            } else {
                $el.text(text);
            }
            this.$messages.append($el);
            return $el;
        },

        // Three pulsing dots instead of a static "Thinking..." label, while waiting for a
        // reply - the fixed markup below has no user input in it, so .html() (rather than
        // the escaping .text() appendMessage() uses for real message content) is safe here.
        appendTypingIndicator: function () {
            var $el = $('<div class="wdp-ai-chat-message wdp-ai-chat-message-assistant wdp-ai-chat-pending"></div>')
                .attr('aria-label', cfg.i18n.typing)
                .html('<span class="wdp-ai-chat-typing-dots"><span></span><span></span><span></span></span>');
            this.$messages.append($el);
            return $el;
        },

        scrollToBottom: function () {
            this.$messages.scrollTop(this.$messages[0].scrollHeight);
        },

        // Inserts a drafted (or edited) rule into the rules list live, using the same
        // window.wdpAddRule (= rules.js's own add_rule) the page itself uses on
        // load, then expands it with the same data. Expanding via a plain
        // .trigger('click') would call wpc_postboxes' click handler with no
        // data argument, which makes rules.js's finishLoadRule() fail to find
        // this (unsaved, not-yet-in-wdp_data.rules) rule and flush the row's
        // fields instead of populating them - hence calling _on_expand directly
        // with ruleData here, so it runs setRuleData() like a normal reload would.
        //
        // When ruleData.id matches a row already in the list (the backend edited a SAVED
        // rule instead of drafting a new one), that row is swapped out for a fresh one at
        // the same position. An edit of a rule that has no id yet (an unsaved draft the
        // chat itself inserted earlier in this same conversation) can't be matched by id -
        // the backend instead says so explicitly via `edited`, and contextForm (the exact
        // DOM row this message was sent about, captured by send() before the reply came
        // back) is used to find that row instead.
        //
        // Either way, rules.js's finishLoadRule() guards against re-populating an
        // already-initialized row (it would just append more filter/condition rows on top
        // of the existing ones rather than replacing them), so a plain re-expand can't
        // apply the edit - a clean not-initialized row is the only way to render the
        // updated data correctly. The id (or lack of one) carries over either way, so
        // clicking Save still updates the existing DB row rather than creating a duplicate.
        //
        // titles/links (both optional) are wdp_data.titles / wdp_data.links-shaped
        // maps, e.g. {products: {"88": "#88 Sunglasses"}} - merged into the page's
        // own lookup tables before rendering, because rules.js's product/category
        // picker reads display names (and, with the Pro "products as links" option,
        // edit links) from those tables and falls back to showing the raw id for
        // anything not already in them (which a brand new, never-before-used
        // product/category never is).
        insertCreatedRule: function (ruleData, titles, links, edited, contextForm) {
            if (typeof window.wdpAddRule !== 'function') {
                return;
            }

            this.mergeIntoWdpData('titles', titles);
            this.mergeIntoWdpData('links', links);

            var $existing = null;
            if (ruleData.id) {
                $existing = this.findRuleRow(ruleData.id);
            } else if (edited && contextForm && contextForm.length && contextForm.closest('#rules-container').length) {
                $existing = contextForm;
            }

            var insertIndex = null;
            if ($existing && $existing.length) {
                insertIndex = $existing.index();
                // Plain jQuery .remove() runs jQuery.cleanData() on the whole subtree,
                // which fires the jQuery Mobile flipswitch widget's bound "remove" event
                // handler (the enabled/disabled toggle) to auto-destroy itself - and that
                // destroy path throws ("this.removeClass is not a function") for a row
                // that's being swapped out live like this, aborting this entire function
                // before it ever reaches wdpAddRule() below. Removing the node via the raw
                // DOM API instead skips jQuery's data/event cleanup (and so the widget's
                // auto-destroy) entirely - harmless here since the whole subtree is being
                // discarded and replaced anyway.
                $existing.each(function () {
                    if (this.parentNode) {
                        this.parentNode.removeChild(this);
                    }
                });
            }

            // wdpAddRule() (= rules.js's add_rule()) appends the row to #rules-container
            // BEFORE it initializes any of its widgets (e.g. the enabled/disabled flipswitch
            // toggle) - a widget init/refresh throwing partway through (seen with jQuery
            // Mobile's flipswitch specifically) would otherwise abort this whole function
            // with an uncaught exception, silently skipping removeClass('closed') and the
            // _on_expand() call below that actually populates filters/discounts/etc. The row
            // itself is already in the DOM by the time any widget code runs, so on a throw
            // here, recover it by element rather than losing the update entirely.
            var $newRule;
            try {
                $newRule = window.wdpAddRule(ruleData);
            } catch (e) {
                if (window.console && console.error) {
                    console.error('wdpAddRule() threw while adding/updating a rule row - continuing with the row it already appended', e);
                }
                $newRule = $('#rules-container .wdp-ruleitem').last();
            }

            if (!$newRule || !$newRule.length) {
                return;
            }

            if (insertIndex !== null) {
                var $target = $('#rules-container').children().eq(insertIndex);
                if ($target.length && $target[0] !== $newRule[0]) {
                    $newRule.insertBefore($target);
                }
            }

            $newRule.removeClass('closed');

            try {
                if (window.wpc_postboxes && typeof window.wpc_postboxes._on_expand === 'function') {
                    window.wpc_postboxes._on_expand($newRule, ruleData);
                }
            } catch (e) {
                if (window.console && console.error) {
                    console.error('_on_expand() threw while populating the rule row', e);
                }
            }

            $('html, body').animate({ scrollTop: $newRule.offset().top - 100 }, 'slow');
        },

        findRuleRow: function (id) {
            return $('#rules-container .wdp-ruleitem').filter(function () {
                return $(this).find('.rule-id').val() === String(id);
            });
        },

        mergeIntoWdpData: function (key, map) {
            if ( ! map || ! window.wdp_data) {
                return;
            }

            window.wdp_data[key] = window.wdp_data[key] || {};
            $.each(map, function (type, idsToValues) {
                window.wdp_data[key][type] = $.extend({}, window.wdp_data[key][type], idsToValues);
            });
        },

        send: function () {
            var self = this;
            var text = $.trim(this.$input.val());

            if (!text) {
                return;
            }

            this.$messages.find('.wdp-ai-chat-empty').remove();
            this.appendMessage('user', text);
            var $pending = this.appendTypingIndicator();
            this.scrollToBottom();

            this.$input.val('').prop('disabled', true);
            this.$send.prop('disabled', true);

            var payload = { message: text, tab: cfg.tab };
            // captured now (not re-read from this.currentRule in the callback below) so a
            // reply that arrives after the admin has since opened/closed a different rule
            // still gets matched back to the row this particular message was actually about
            var contextForm = null;
            if (this.currentRule) {
                if (this.currentRule.id) {
                    payload.rule_id = this.currentRule.id;
                }
                // the live, possibly-unsaved form state of the open rule - serialized fresh
                // right now, not a stale snapshot - so the AI reasons about what's actually
                // on screen (including any earlier, still-unsaved AI edits or manual tweaks),
                // not whatever was last saved to the database. Sent even when there's no id
                // yet (a rule the chat itself just drafted, not saved) - that's still a real,
                // currently-open rule as far as editing it goes.
                if (this.currentRule.$form && this.currentRule.$form.length) {
                    contextForm = this.currentRule.$form;
                    payload.rule_state = contextForm.serialize();
                }
            }

            post('ai_chat_send', payload, function (data) {
                $pending.removeClass('wdp-ai-chat-pending').removeAttr('aria-label').html(messageHtml(data.assistant.message));
                self.$input.prop('disabled', false);
                self.$input.trigger('focus');
                self.scrollToBottom();

                if (data.rule) {
                    self.insertCreatedRule(data.rule, data.titles, data.links, data.edited, contextForm);
                }

                if (data.go_to_rules && cfg.rules_url) {
                    // rules can only be drafted on the Rules tab (that's where the list they
                    // get inserted into lives) - the conversation is shared across tabs, so
                    // the request can simply be confirmed there
                    $pending.append('<br>').append(
                        $('<a class="wdp-ai-chat-message-link"></a>').attr('href', cfg.rules_url).text(cfg.i18n.open_rules_tab)
                    );
                    self.scrollToBottom();
                }
            }, function () {
                $pending.removeClass('wdp-ai-chat-pending').removeAttr('aria-label').addClass('wdp-ai-chat-error').text(cfg.i18n.error);
                self.$input.prop('disabled', false);
            });
        },
    };

    $(function () {
        if (!cfg || !cfg.i18n) {
            return;
        }
        AiChat.init();
    });
})(jQuery);
