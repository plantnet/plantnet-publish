// jQuery ToolTips
// Version: 1.1.1, Last updated: 2/27/2011
//
// GitHub       - https://github.com/thinkdevcode/jQuery-ToolTip
// Contact      - gin4lyfe@gmail.com (Eugene Alfonso)
// 
// See License.txt for full license
// 
// Copyright (c) 2011 Eugene Alfonso,
// Licensed under the MIT license.

(function ($) {

    /*
    *   tooltip() - Applies custom tooltip to jQuery objects using either 'title' attribute or
    *               a custom text provided in parameter.
    *
    *       options [object] [optional]
    *           A map of settings... { container(string or object), img, text, width, 
    *                                  height, follow, offset { top, left } }
    *
    */
    $.fn.tooltip = function (options) {

        var defaults = {
            container: undefined,
            img: undefined,
            imgdim: { height: 300, width: 250 },
            text: undefined,
            width: undefined,
            height: undefined,
            follow: undefined,
            offset: { top: 10, left: 15 }
        };
        $.extend(defaults, options); //apply any settings the user may have

        var cont = $('#ttContainerDiv');

        //create container if does not exist
        if (!cont.length || cont.length <= 0) {

            //this is our html markup for tooltip
            var markup = '<div id="ttContainerDiv" class="ui-corner-all ui-widget-content ui-widget" style="padding:1em;display:none;position:fixed"> \
                        <span id="ttText"></span></div>';

            //append the container to other container (default 'body')
            if (typeof defaults.container === 'string') {
                $(markup).appendTo(defaults.container);
            } else if (defaults.container instanceof jQuery) {
                defaults.container.append(markup);
            } else {
                $(markup).appendTo('body');
            }

            cont = $('#ttContainerDiv');
        }

        var span = $('#ttText');

        //loop through our jquery objects
        this.each(function () {

            var tooltiptext = $(this).attr('title'); //grab the title for future use

            //make sure we have something for our tooltip
            if (tooltiptext || defaults.text || defaults.img) {
                $(this).hover(
                    function (e) { //mouseenter

                        $(this).attr('title', ''); //remove the title so original alt doesnt show
                        cont.css({ 'width': '', 'height': '' }); //reset the height and width
                        cont.children('img').remove();
                        if (defaults.img) {
                            cont.prepend('<img src="' + defaults.img + '" id="ttImage" height="' + defaults.imgdim.height + 'px" width="' + defaults.imgdim.width + 'px" />');
                            span.hide();
                        } else {
                            span.text(((defaults.text) ? defaults.text : tooltiptext));
                            span.show();
                        }

                        //only change sizes if container is larger than it should be (this is why we reset the height and width)
                        if (defaults.width) { if (cont.width() > defaults.width) { cont.width(defaults.width); } }
                        if (defaults.height) { if (cont.height() > defaults.height) { cont.height(defaults.height); } }

                        if (defaults.follow) {
                            $(this).mousemove(function (e) {
                                cont.css({ 'top': e.clientY + defaults.offset.top, 'left': e.clientX + defaults.offset.left });
                            });
                        } else {
                            cont.css({ 'top': e.clientY + defaults.offset.top, 'left': e.clientX + defaults.offset.left });
                        }
                        cont.show();
                    },
                    function (e) { //mouseexit
                        if (defaults.follow) { $(this).unbind('mousemove'); }
                        cont.hide();
                        span.text(''); //reset text
                        $(this).attr('title', tooltiptext); //return title to what it was before
                    }
                );
            }
        });
        return this;
    };
})(jQuery);