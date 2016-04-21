(function(ui, $) {

    /**
     * sets up event handlers ; dataloaded not fired yet
     * @memberOf ui
     */
    ui.setup_event_handlers = function() {
        $('#decklist-delete').on('click', ui.delete_form);
        $('.social .social-icon-like').on('click', ui.send_like);
        $('.social .social-icon-favorite').on('click', ui.send_favorite);
        $('#btn-group-decklist button[id], a[id]').on('click', ui.do_action_decklist);
        $('#btn-compare').on('click', ui.compare_form);
        $('#btn-compare-submit').on('click', ui.compare_submit);
    };

    ui.delete_form = function() {
        $('#deleteModal').modal('show');
    };

    ui.do_action_decklist = function(event) {
        var action_id = $(this).attr('id');
        if (!action_id) {
            return;
        }

        switch (action_id) {
            case 'btn-download-text':
                location.href = Routing.generate('decklist_export_text', { decklist_id: app.deck.get_id() });
                break;

            case 'btn-download-octgn':
                location.href = Routing.generate('decklist_export_octgn', { decklist_id: app.deck.get_id() });
                break;

            case 'btn-sort-type':
                ui.refresh_deck({
                    sort: 'type',
                    cols: 2
                });
                break;

            case 'btn-sort-position':
                ui.refresh_deck({
                    sort: 'position',
                    cols: 1
                });
                break;

            case 'btn-sort-sphere':
                ui.refresh_deck({
                    sort: 'sphere',
                    cols: 1
                });
                break;

            case 'btn-sort-name':
                ui.refresh_deck({
                    sort: 'name',
                    cols: 1
                });
                break;

            case 'btn-export-bbcode':
                app.deck.export_bbcode();
                break;

            case 'btn-export-markdown':
                app.deck.export_markdown();
                break;

            case 'btn-export-plaintext':
                app.deck.export_plaintext();
                break;

            case 'btn-export-html':
                app.deck.export_html();
                break;
        }
    };

    ui.send_like = function(event) {
        event.preventDefault();
        var that = $(this);

        if (that.hasClass('processing')) {
            return;
        }

        that.addClass('processing');

        $.post(Routing.generate('decklist_like'), {
            id: app.deck.get_id()
        }, function(data, textStatus, jqXHR) {
            that.find('.num').text(data);
            that.removeClass('processing');
        });
    };

    ui.send_favorite = function(event) {
        event.preventDefault();

        var that = $(this);

        if (that.hasClass('processing')) {
            return;
        }

        that.addClass('processing');

        $.post(Routing.generate('decklist_favorite'), {
            id: app.deck.get_id()
        }, function(data, textStatus, jqXHR) {
            that.find('.num').text(data);

            var title = that.attr('data-original-title');
            that.attr('data-original-title', title == "Add to favorites" ? "Remove from favorites" : "Add to favorites");
            that.removeClass('processing');
        });

        ui.send_like.call($('.social .social-icon-like'), event);
    };

    ui.setup_comment_form = function() {
        var form = $(
            '<form method="POST" action="' + Routing.generate('decklist_comment') + '"><input type="hidden" name="id" value="' + app.deck.get_id() + '"><div class="form-group">' +
            '<textarea id="comment-form-text" class="form-control" rows="4" name="comment" placeholder="Enter your comment in Markdown format. Type # to enter a card name. Type $ to enter a symbol. Type @ to enter a user name."></textarea>' +
            '</div><div class="well text-muted" id="comment-form-preview"></div><button type="submit" class="btn btn-success">Submit comment</button></form>'
        ).insertAfter('#comment-form');

        var already_submitted = false;
        form.on('submit', function(event) {
            event.preventDefault();

            var data = $(this).serialize();

            if (already_submitted) {
                return;
            }

            already_submitted = true;

            $.ajax(Routing.generate('decklist_comment'), {
                data: data,
                type: 'POST',
                success: function(data, textStatus, jqXHR) {
                    form.replaceWith('<div class="alert alert-success" role="alert">Your comment has been posted. It will appear on the site in a few minutes.</div>');
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.log('[' + moment().format('YYYY-MM-DD HH:mm:ss') + '] Error on ' + this.url, textStatus, errorThrown);
                    form.replaceWith('<div class="alert alert-danger" role="alert">An error occured while posting your comment (' + jqXHR.statusText + '). Reload the page and try again.</div>');
                }
            });
        });

        $('.social .social-icon-comment').on('click', function() {
            $('#comment-form-text').trigger('focus');
        });

        app.markdown.setup('#comment-form-text', '#comment-form-preview');
        app.textcomplete.setup('#comment-form-text', {
            cards: true,
            icons: true,
            users: Commenters
        });
    };

    ui.setup_social_icons = function() {
        if (!app.user.data || app.user.data.is_author || app.user.data.is_liked) {
            var element = $('.social .social-icon-like');
            element.replaceWith($('<span class="social-icon-like"></span>').html(element.html()));
        }

        if (!app.user.data) {
            var element = $('.social .social-icon-favorite');
            element.replaceWith($('<span class="social-icon-favorite"></span>').html(element.html()));
        } else if (app.user.data.is_favorite) {
            var element = $('.social .social-icon-favorite');
            element.attr('data-original-title', "Remove from favorites");
        } else {
            var element = $('.social .social-icon-favorite');
            element.attr('data-original-title', "Add to favorites");
        }

        if (!app.user.data) {
            var element = $('.social .social-icon-comment');
            element.replaceWith($('<span class="social-icon-comment"></span>').html(element.html()));
        }
    };

    ui.add_author_actions = function() {
        if (app.user.data && app.user.data.is_author) {
            $('#decklist-edit').show();
            if (app.user.data.can_delete) {
                $('#decklist-delete').show();
            } else {
                $('#decklist-delete').remove();
            }
        } else {
            $('#decklist-edit').remove();
            $('#decklist-delete').remove();
        }
    };

    ui.setup_comment_hide = function() {
        if (app.user.data && app.user.data.is_author) {
            $('.comment-hide-button').remove();
            $('<a href="#" class="comment-hide-button"><span class="text-danger fa fa-times" style="margin-left:.5em"></span></a>').appendTo('.collapse.in .comment-date').on('click', function(event) {
                if (confirm('Do you really want to hide this comment for everybody?')) {
                    ui.hide_comment($(this).closest('td'));
                }
                return false;
            });
            $('<a href="#" class="comment-hide-button"><span class="text-success fa fa-check" style="margin-left:.5em"></span></a>').appendTo('.collapse:not(.in) .comment-date').on('click', function(event) {
                if (confirm('Do you really want to unhide this comment?')) {
                    ui.unhide_comment($(this).closest('td'));
                }
                return false;
            });
        }
    };

    ui.hide_comment = function(element) {
        var id = element.attr('id').replace(/comment-/, '');
        $.ajax(Routing.generate('decklist_comment_hide', {comment_id: id, hidden: 1}), {
            type: 'POST',
            dataType: 'json',
            success: function(data, textStatus, jqXHR) {
                if (data === true) {
                    element.find('.collapse').collapse('hide');
                    element.find('.comment-toggler').show().prepend('The comment will be hidden for everyone in a few minutes.');
                    setTimeout(ui.setup_comment_hide, 1000);
                } else {
                    alert(data);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log('[' + moment().format('YYYY-MM-DD HH:mm:ss') + '] Error on ' + this.url, textStatus, errorThrown);
                alert('An error occured while hiding this comment (' + jqXHR.statusText + '). Reload the page and try again.');
            }
        });
    };

    ui.unhide_comment = function(element) {
        var id = element.attr('id').replace(/comment-/, '');
        $.ajax(Routing.generate('decklist_comment_hide', {comment_id: id, hidden: 0}), {
            type: 'POST',
            dataType: 'json',
            success: function(data, textStatus, jqXHR) {
                if (data === true) {
                    element.find('.collapse').collapse('show');
                    element.find('.comment-toggler').hide();
                    setTimeout(ui.setup_comment_hide, 1000);
                } else {
                    alert(data);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log('[' + moment().format('YYYY-MM-DD HH:mm:ss') + '] Error on ' + this.url, textStatus, errorThrown);
                alert('An error occured while unhiding this comment (' + jqXHR.statusText + '). Reload the page and try again.');
            }
        });
    };

    /**
     * @memberOf ui
     */
    ui.refresh_deck = function(options) {
        app.deck.display('#deck-content', options, false);
        app.deck.display('#sideboard-content', options, true);
        app.deck_charts && app.deck_charts.setup();
    };

    /**
     * called when the DOM is loaded
     * @memberOf ui
     */
    ui.on_dom_loaded = function on_dom_loaded() {
        ui.setup_event_handlers();
        app.draw_simulator && app.draw_simulator.on_dom_loaded();
    };

    /**
     * called when the app data is loaded
     * @memberOf ui
     */
    ui.on_data_loaded = function on_data_loaded() {
    };

    /**
     * called when both the DOM and the data app have finished loading
     * @memberOf ui
     */
    ui.on_all_loaded = function on_all_loaded() {
        app.draw_simulator && app.draw_simulator.reset();

        app.user.loaded.done(function() {
            ui.setup_comment_form();
            ui.add_author_actions();
            ui.setup_comment_hide();
        }).fail(function() {
            $('<p>You must be logged in to post comments.</p>').insertAfter('#comment-form');
        }).always(function() {
            ui.refresh_deck();
            ui.setup_social_icons();
        });
    };

})(app.ui, jQuery);
