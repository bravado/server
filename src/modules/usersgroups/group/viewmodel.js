/*global define: true, confirm: true, $: true, ko: true, metaproject: true, console: true */
define(['Boiler'], function (Boiler) {

    "use strict";
    var ViewModel = function (moduleContext) {

        var self = this,
            dialog;

        self.model = ko.observable();

        self.close = function () {
            Boiler.UrlController.goTo("users");
        };

        self.load = function (id) {
            if (id) {
                moduleContext.ds.get(id, self.model);
            }
            else {
                self.model(moduleContext.ds.create(moduleContext.threadParams));
            }
        };


        self.destroy = function (vm, event) {
            if (event.currentTarget.dataset.confirm && !confirm(event.currentTarget.dataset.confirm)) {
                return;
            }

            moduleContext.ds.destroy(self.model(), function () {
                if (event.currentTarget.dataset.close === "true") {
                    self.close();
                }
            });

        };


        self.save = function (vm, event) {
            moduleContext.ds.save(self.model(), function () {
                if (event.currentTarget.dataset.close === "true") {
                    self.close();
                }
            });
        };

    };

    return ViewModel;
});
