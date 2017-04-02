(function () {

var aliases = {};
var socket;

/**
* Log manager
*
* @constructor
*/
function Logger() {

this.isDebugMode = false;

var console = window.console || {
info : function () {},
log : function () {},
error : function () {}
};

if (!console['log']) {
console.log = function () {}
}

if (!console['error']) {
console.error = function () {}
}

if (!console['info']) {
console.info = function () {}
}

this.log = function (message) {
if (this.isDebugMode) {
var d=new Date();
var formattedDate=("0" + d.getDate()).slice(-2) + "-" + ("0" + (d.getMonth() + 1)).slice(-2) + "-" +
d.getFullYear() + " " + ("0" + d.getHours()).slice(-2) + ":" + ("0" + d.getMinutes()).slice(-2);
console.log(formattedDate + ': ' + message);
}
};

this.info = function () {
if (this.isDebugMode) {
console.info.apply(console, arguments);
}
};

this.error = function () {
if (this.isDebugMode) {
console.error.apply(console, arguments);
}
};
}

var logger = new Logger();

/**
* Hooked event listen manager
*
* @param scope
* @constructor
*/
function EventHookManager(scope) {

var events = {
before : {},
after : {}
};

this.trigger = function (when, event, data) {
logger.log('Triggered hook manager, when - ' + when + ', event - ' + event);
if (events[when] && events[when][event]) {
for (var i in events[when][event]) {
var executionResult = events[when][event][i].call(scope, data, event);
if (executionResult === false) {
return false;
break;
}
}
}
return true;
};

/**
*
* @param event
* @param fn
* @returns {*}
*/
this.before = function (event, fn) {
if (!events.before[event]) {
events.before[event] = [];
}
logger.log('Add new hook: when - before, event - ' + event);
events.before[event].push(fn);
return this;
};

/**
*
* @returns {*}
*/
this.after = function (event, fn) {
if (!events.after[event]) {
events.after[event] = [];
}
logger.log('Add new hook: when - after, event - ' + event);
events.after[event].push(fn);
return this;
};

var self = this;

scope.after = function () {
self.after.apply(self, arguments);
};
scope.before = function () {
self.before.apply(self, arguments);
};
}

function EventEmitter(owner, scope) {

logger.log(
'Creating new event emitter with scope ' +
scope.type +
(scope.type == 'global' ? '' : ':' + scope.id)
);

/**
*
* @param event
* @param data
*/
owner.emit = function (event, data) {
logger.log('Emit event `' + event + '` in scope ' + scope.type + (scope.type == 'global' ? '' : ':' + scope.id));
socket.emit('event:emit', {
'event' : event,
'scope' : scope,
'data' : data
});
};
}

/**
* Event listen manager
*
* @param {object} owner
* @param eventPrefix
* @constructor
*/
function EventListener(owner, eventPrefix) {

/**
* Add before event
*
* @name before
* @function
* @memberOf EventHookManager
*/

/**
* Add after event
*
* @name after
* @function
* @memberOf EventHookManager
*/

eventPrefix = eventPrefix || '';
var self = this;
var eventHookListener = new EventHookManager(owner);
var events = {};

/**
*
* @returns {Object}
*/
this.getOwner = function () {
return owner || this;
};

/**
*
* @returns {*}
*/
this.getId = function () {
if (this.getOwner() === this) {
return '';
}
return this.getOwner().getId();
};

this.getHookListener = function () {
return eventHookListener;
};

this.setEventPrefix = function (prefix) {
logger.log('Set event prefix to: ' + prefix);
eventPrefix = prefix;
};

this.emit = function (event, data) {
logger.log('Tying to fire event: ' + event);
if (eventHookListener.trigger('before', event, data)) {
if (events[event]) {
var chain = {
'break' : false
};
for (var i in events[event]) {
events[event][i].call(owner, data, chain);
if (chain['break'] === true) {
logger.log('Handler of event: ' + event + ' break event chain');
break;
}
}
} else {
logger.log('Event [' + event + '] has no listeners');
}
eventHookListener.trigger('after', event, data);
}
logger.log('Event [' + event + '] processed!');
return owner;
};

var getInternalEventName = function (event) {
var id = self.getId();
if (id == '') {
return eventPrefix + ':' + event;
}
return eventPrefix + id + ':' + event;
};

/**
*
* @param event
* @param fn
* @returns {*}
*/
this.on = function (event, fn) {
logger.log('Attach event listener for event: ' + event);
if (!events[event]) {
events[event] = [];
}
events[event].push(fn);
var self = this;
var ev = getInternalEventName(event);
logger.log('Compiled event name: ' + ev);
socket.on(ev, function (data) {
logger.log('Received data for event: ' + event);
self.emit(event, data);
});
return owner;
};

/**
*
* @param event
* @param fn
* @returns {*}
*/
owner.on = function (event, fn) {
self.on(event, fn);
};
}

function SystemEventHandlers() {

var self = this;

var handlers = {
'invoke' : function (frame) {
if (frame['functions'] && typeof frame['functions'] == 'object') {
for (var i in frame['functions']) {
var func = frame['functions'][i]['function'];
var args = frame['functions'][i]['arguments'];
var scope = frame['functions'][i]['scope'];
if (window[func] && typeof window[func] == 'function') {
if (scope && window[scope]) {
window[func].apply(window[scope], args);
} else {
window[func].apply(window, args);
}
}
}
}
if (frame['methods'] && typeof frame['methods'] == 'object') {
for (var i in frame['methods']) {
var func = frame['methods'][i]['method'];
var args = frame['methods'][i]['arguments'];
var scope = frame['methods'][i]['scope'];
var obj = frame['methods'][i]['object'];
if (window[obj] && typeof window[obj] == 'object') {
if (typeof window[obj][func] == 'function') {
if (scope && typeof window[scope] == 'object') {
window[obj][func].apply(window[scope], args);
} else {
window[obj][func].apply(window[obj], args);
}
}
}
}
}
},

'jquery' : function (queries) {
for (var i in queries) {
var query = queries[i];
if (query.beforeApply && window[query.beforeApply]) {
if (window[query.beforeApply]() === false) {
continue;
}
}
$(query.selector).is(function () {
var $this = $(this);
for (var i in query.actions) {
var action = query.actions[i].action;
var args = query.actions[i].arguments;
switch (action) {

case 'remove':
$this.remove();
return;
break;

case 'parents':
case 'find':
case 'parent':
case 'next':
case 'prev':
case 'children':
$this = $this[action].apply($this, args);
break;

default:
$this[action].apply($this, args);
break;
}
}
});
if (query.afterApply && window[query.afterApply]) {
window[query.afterApply]();
}
}
}
};

var bind = function (event, handler, binder) {
binder.on(event, function (data) {
logger.log('Catch system event ' + event);
handlers[event].call(this, data);
});
};

this.attachTo = function (binder) {
for (var event in handlers) {
bind(event, handlers[event], binder);
}
};
}



function SystemEventsHandlerBinder(owner) {

var systemEventHandlers = new SystemEventHandlers();

/**
* Attach event handler event
*
* @name on
* @function
* @memberOf EventListener
*/

var eventListener = new EventListener(this);

/**
*
* @returns {string}
*/
this.getId = function () {
return 'system';
};

/**
*
* @returns {*}
*/
this.getOwner = function () {
return owner;
};

systemEventHandlers.attachTo(this);
}

function NodeSocket(token) {

/**
* Emit event
*
* @name emit
* @function
* @memberOf EventEmitter
*/

/**
* Add before event
*
* @name before
* @function
* @memberOf EventHookManager
*/

/**
* Add after event
*
* @name after
* @function
* @memberOf EventHookManager
*/

/**
* Attach event handler event
*
* @name on
* @function
* @memberOf EventListener
*/

var self = this;

this._eventListener = new EventListener(this, 'global');
this._eventEmitter = new EventEmitter(this, {
'type' : 'global'
});

socket = io.connect('http://<?php echo $configs['host'];?>:<?php echo $configs['port'];?>', {
query: 'token=' + token
});

var systemEventsHandlerBinder = new SystemEventsHandlerBinder(this);

this.getId = function () {
return '';
};

this.debug = function (flag) {
logger.isDebugMode = flag;
return this;
};

/**
*
* @returns {Logger}
*/
this.getLogger = function () {
return logger;
};


this.onConnect = function (fn) {
if (fn) {
socket.on('connect', fn);
}
};

this.onDisconnect = function (fn) {
if (fn) {
socket.on('disconnect', fn);
}
};

this.onConnecting = function (fn) {
if (fn) {
socket.on('connecting', fn);
}
};

this.onReconnect = function (fn) {
if (fn) {
socket.on('reconnect', fn);
}
};

this.onMessage = function (fn) {
if (fn) {
socket.on('message', fn);
}
};

this.onNotification = function (fn) {
if (fn) {
socket.on('notification', fn);
}
};

this.onError = function (fn) {
if (fn) {
socket.on('error', fn);
}
};
}

window.NodeSocket = NodeSocket;
})();
