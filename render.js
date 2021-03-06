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
        console.log("<!-- page.childFramesCount=" + page.childFramesCount() + " in the first level -->");
        if (page.childFramesCount() > 0) {
            for (var i = 0; i < page.childFramesCount(); i++) {
                console.log("<!-- i=" + i + " -->");
                page.switchToChildFrame(i);
                
                console.log("<!-- page.childFramesCount=" + page.childFramesCount() + " in the second level -->");
                if (page.childFramesCount() > 0) {
                    for (var j = 0; j < page.childFramesCount(); j++) {
                        console.log("<!-- j=" + j + " -->");
                        page.switchToChildFrame(j);
                        render(page);
                        page.switchToParentFrame();
                    }
                }
                render(page);
                page.switchToParentFrame();
            }
       } else {
            render(page);
        }
		phantom.exit(0);
	}
});
