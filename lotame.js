! function() {
    var lotameClientId = '159822';
    var lotameTagInput = {
        data: {},
        config: {
            clientId: Number(15982)
        }
    };

    // Lotame initialization
    var lotameConfig = lotameTagInput.config || {};
    var namespace = window['lotame_' + lotameConfig.clientId] = {};
        namespace.config = lotameConfig;
        namespace.data = lotameTagInput.data || {};
        namespace.cmd = namespace.cmd || [];
    } ();