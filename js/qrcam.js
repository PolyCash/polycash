function QRCam(camDivId, uploadDivId, qrCanvasId, qrFoundFunction) {
	_this = this;
	this.camMode = true; // Camera mode or upload mode
	this.refreshDelay = 500;
	
	this.findCount = 0;
	this.stringFinds = new Array();
	
	this.camDivId = camDivId;
	this.uploadDivId = uploadDivId;
	this.canvasDivId = qrCanvasId;
	this.gCanvas = document.getElementById(qrCanvasId);
	
	this.gUM = false;
	this.gCtx = null;
	
	this.stype = 0;
	this.webkit = false;
	this.moz = false;
	
	this.qrFoundFunction = qrFoundFunction;
	
	this.imghtml = '';
	this.vidhtml = '<video id="v" autoplay></video>';
};

QRCam.prototype = {
	selectOS: function(OSName) {
		if (OSName == 'pc') {
			this.camMode = true;
			$('#'+this.camDivId).show('fast');
			$('#'+this.uploadDivId).hide('fast');
		}
		else {
			this.camMode = false;
			$('#'+this.uploadDivId).show('fast');
			$('#'+this.camDivId).hide('fast');
		}
		this.loadWebQR();
	},
	loadWebQR: function() {
		if (this.isCanvasSupported() && window.File && window.FileReader) {
			this.initCanvas(800, 600);
			qrcode.callback = function(foundString) {
				_this.webQRSuccess(foundString);
			};
			if (this.camMode) this.setWebCam();
		}
		else {
			alert('switch to upload');
		}
	},
	setWebCam: function() {
		if (_this.stype==1) {
			setTimeout(_this.captureToCanvas, this.refreshDelay);    
			return;
		}
		var n=navigator;
		document.getElementById("outdiv").innerHTML = _this.vidhtml;
		v=document.getElementById("v");

		if(n.getUserMedia)
			n.getUserMedia({video: true, audio: false}, this.success, this.error);
		else if (n.webkitGetUserMedia) {
			_this.webkit=true;
			n.webkitGetUserMedia({video: true, audio: false}, this.success, this.error);
		}
		else if (n.mozGetUserMedia) {
			_this.moz=true;
			n.mozGetUserMedia({video: true, audio: false}, this.success, this.error);
		}
		
		_this.stype=1;
		setTimeout(_this.captureToCanvas, this.refreshDelay);
	},
	initCanvas: function(w, h) {
		_this.gCanvas = document.getElementById(qrCanvasId);
		_this.gCanvas.style.width = w + "px";
		_this.gCanvas.style.height = h + "px";
		_this.gCanvas.width = w;
		_this.gCanvas.height = h;
		_this.gCtx = _this.gCanvas.getContext("2d");
		_this.gCtx.clearRect(0, 0, w, h);
	},
	handleFiles: function(f) {
		var o=[];
		
		for (var i=0; i<f.length; i++) {
			var reader = new FileReader();
			reader.onload = (function(theFile) {
				return function(e) {
					_this.gCtx.clearRect(0, 0, _this.gCanvas.width, _this.gCanvas.height);
					qrcode.decode(e.target.result);
				};
			})(f[i]);
			reader.readAsDataURL(f[i]);	
		}
	},
	captureToCanvas: function() {
		if (_this.stype != 1) return;
		if (_this.gUM) {
			try {
				_this.gCtx.drawImage(v, 0, 0);
				try {
					qrcode.decode();
				}
				catch(e){			 
					console.log(e);
					setTimeout(_this.captureToCanvas, this.refreshDelay);
				};
			}
			catch(e){
				console.log(e);
				setTimeout(_this.captureToCanvas, this.refreshDelay);
			};
		}
	},
	isCanvasSupported: function() {
		var elem = document.createElement('canvas');
		return !!(elem.getContext && elem.getContext('2d'));
	},
	success: function(stream) {
		if (_this.webkit)
			v.src = window.webkitURL.createObjectURL(stream);
		else if (_this.moz) {
			v.mozSrcObject = stream;
			v.play();
		}
		else v.src = stream;
		_this.gUM = true;
		setTimeout(_this.captureToCanvas, this.refreshDelay);
	},
	error: function(error) {
		_this.gUM = false;
		return;
	},
	htmlEntities: function(str) {
		return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	},
	webQRSuccess: function(foundString) {
		this.stringFinds[this.findCount] = this.htmlEntities(foundString);
		this.findCount++;
		
		this.qrFoundFunction(foundString);
		
		this.loadWebQR();
	}
}