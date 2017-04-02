var activeClients = [], workers = [];
(function () {
    var express = require('express'),
        app = express(),
        http = require('http'),
        server = http.createServer(app),
        io = require('socket.io').listen(server),
        RSMQWorker = require("rsmq-worker"),
        bodyParser = require('body-parser'),
        jwt = require('jsonwebtoken'),
        socketioJwt = require('socketio-jwt'),
        secret = "my_super_secret_key",
        winston = require('winston'),
        serverConfiguration = require('./server.config.js');

    app.use(bodyParser.json());       // to support JSON-encoded bodies
    app.use(bodyParser.urlencoded({     // to support URL-encoded bodies
        extended: true
    }));

    //custom winston logger definition
    var logger = new (winston.Logger)({
        transports: [
            new (winston.transports.Console)({
                timestamp: function () {
                    var d = new Date();
                    return ("0" + d.getDate()).slice(-2) + "-" + ("0" + (d.getMonth() + 1)).slice(-2) + "-" +
                    d.getFullYear() + " " + ("0" + d.getHours()).slice(-2) + ":" + ("0" + d.getMinutes()).slice(-2);
                },
                formatter: function (options) {
                    // Return string will be passed to logger.
                    return options.timestamp() + ' [' + options.level.toUpperCase() + '] ' + (options.message ? options.message : '') +
                    (options.meta && Object.keys(options.meta).length ? '\n\t' + JSON.stringify(options.meta) : '' );
                }
            })
        ]
    });

    app.post('/login', function (req, res) {

        var profile = {
            id: req.body.userId,
            qname: req.body.qname
        };

        // we are sending the profile in the token
        var token = jwt.sign(profile, secret, {expiresIn: '2 days'});

        res.json({token: token});
    });

// middleware to perform socket authorization
    io.use(socketioJwt.authorize({
        secret: secret,
        handshake: true,
        ignoreExpiration: true
    }));

//listening on connections
    io.sockets.on('connection', function (socket) {

        var userProfile = socket.decoded_token;
        logger.info("New client connected with id: " + userProfile.id);
        var client = getClientById(userProfile.id);

        if (!client) {

            var qname = userProfile.qname;
            var worker = new RSMQWorker(qname, {});
            workers.push({clientId: userProfile.id, qname: qname, worker: worker});

            worker.on("message", function (msg, next, id) {
                msg = JSON.parse(msg);
                var clientId = socket.decoded_token.id;
                var client = getClientById(clientId);
                if (client) {
                    logger.log('Sending message id:' + id + ' to client id: ' + clientId);
                    client['socket'].emit('notification', msg);
                } else {
                    logger.error('Client with id: ' + clientId + ' does not exist.');
                }
                next()
            });

// worker listeners
            worker.on('error', function (err, msg) {
                logger.error(err, msg.id);
            });
            worker.on('exceeded', function (msg) {
                logger.log("[RSMQ WORKER] message exceeded: ", msg.id);
            });
            worker.on('timeout', function (msg) {
                logger.log("[RSMQ WORKER] message timeouted:", msg.id, msg.rc);
            });
            worker.on('delete', function (msg) {
                logger.log("[RSMQ WORKER] message deleted:", msg.id, msg.rc);
            });
            worker.start();
            activeClients.push({clientId: userProfile.id, socket: socket});
        } else {
            activateClientSocket(userProfile.id, socket);
        }

//listening on disconnections
        socket.on('disconnect', function () {
            var clientId = socket.decoded_token.id;
            var worker = getWorkerByClientId(clientId)['worker'];
            if (worker) {
                worker.stop();
            } else {
                logger.error('Worker for client: ' + clientId + ' does not exist.');
            }
        });
    });

    server.listen(serverConfiguration.port, function () {
        logger.info('RSMQ Worker client listening on port: ' + serverConfiguration.port);
    });

}).call(this);


function getClientById(clientId) {
    for (var i = 0; i < activeClients.length; i++) {
        if (activeClients[i]['clientId'] == clientId) {
            return activeClients[i];
        }
    }
    return null;
}

function getClientIndex(clientId) {
    for (var i = 0; i < activeClients.length; i++) {
        if (activeClients[i]['clientId'] == clientId) {
            return i;
        }
    }
    return null;
}

function activateClientSocket(clientId, socket) {
    var clientIndex = getClientIndex(clientId);
    activeClients[clientIndex]['socket'] = socket;
    var worker = getWorkerByClientId(clientId);
    worker['worker'].start();
}

function getWorkerByClientId(clientId) {
    for (var i = 0; i < workers.length; i++) {
        if (workers[i]['clientId'] == clientId) {
            return workers[i];
        }
    }
    return null;
}

/*function emptyQueue(rsmq,qname){
 rsmq.getQueueAttributes({qname:qname},function(err,resp){
 if(resp.msgs>0){    //if there are messages in queue......
 for(var j=0;j<resp.msgs;j++){
 rsmq.popMessage({qname:qname},function(err,resp){
 });
 }
 }
 });
 }*/