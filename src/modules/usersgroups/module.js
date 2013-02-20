/*global define:true, ko: true, metaproject: true, $: true */
define(['Boiler', './menu/component', './user/component', './userlist/component'],
    function (Boiler, MenuComponent, UserComponent, UserListComponent) {
        "use strict";
        var Module = function (globalContext) {


            var context = new Boiler.Context(globalContext);
            //context.addSettings(settings);

            //scoped DomController that will be effective only on $('body')
            var controller = new Boiler.DomController($('body'));
            //add routes with DOM node selector queries and relevant components
            controller.addRoutes({
                ".main-menu" : new MenuComponent(context)
            });
            controller.start();

            controller = new Boiler.UrlController($(".appcontent"));
            controller.addRoutes({
                'users':new UserListComponent(context),
                'user/{id}': new UserComponent(context)
            });
            controller.start();

        };
        return Module;
    });