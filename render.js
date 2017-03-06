var system = require('system');
var page = require('webpage').create();
var url = system.args[1];

var render = function(page) {
    page.evaluate(function() {
        console.log(document.body.innerHTML);
    });
};

page.onConsoleMessage = function(msg) {
	console.log(msg);
};
page.settings.resourceTimeout = 5000;
page.onResourceTimeout = function(e) {
	console.log(e.errorCode);
	console.log(e.errorString);
	console.log(e.url);
	phantom.exit(-1);
};
page.settings.userAgent = 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.37 (KHTML, like Gecko) Chrome/31.0.1650.58 Safari/537.37';

page.open(url, function (status) {
	if (status != 'success') {
		console.log('Unable to access the page, "' + url + '"');
		phantom.exit(-1);
	} else {
        if (page.framesCount > 0) {
            for (var i = 0; i < page.framesCount; i++) {
                page.switchToChildFrame(i);
                render(page);
            }
       } else {
            render(page);
        }
		phantom.exit(0);
	}
});
