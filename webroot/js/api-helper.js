class AJAXApi {
    static genericRequestHeaders = new Headers({
        'X-Requested-With': 'XMLHttpRequest'
    });
    static genericRequestConfigGET = {
        headers: AJAXApi.genericRequestHeaders
    }
    static genericRequestConfigPOST = {
        headers: AJAXApi.genericRequestHeaders,
        redirect: 'manual',
        method: 'POST',
    }

    static defaultOptions = {
        provideFeedback: true,
        statusNode: false,
    }
    options = {}
    loadingOverlay = false

    constructor(options) {
        this.mergeOptions(AJAXApi.defaultOptions)
        this.mergeOptions(options)
    }

    provideFeedback(options, isError=false) {
        if (this.options.provideFeedback) {
            UI.toast(options)
        } else {
            if (isError) {
                console.error(options.body)
            }
        }
    }

    mergeOptions(newOptions) {
        this.options = Object.assign({}, this.options, newOptions)
    }

    static mergeFormData(formData, dataToMerge) {
        for (const [fieldName, value] of Object.entries(dataToMerge)) {
            formData.set(fieldName, value)
        }
        return formData
    }

    static async quickFetchURL(url, options={}) {
        const constAlteredOptions = Object.assign({}, {provideFeedback: false}, options)
        const tmpApi = new AJAXApi(constAlteredOptions)
        return tmpApi.fetchURL(url, constAlteredOptions.skipRequestHooks)
    }

    static async quickFetchForm(url, options={}) {
        const constAlteredOptions = Object.assign({}, {provideFeedback: false}, options)
        const tmpApi = new AJAXApi(constAlteredOptions)
        return tmpApi.fetchForm(url, constAlteredOptions.skipRequestHooks)
    }

    static async quickFetchAndPostForm(url, dataToMerge={}, options={}) {
        const constAlteredOptions = Object.assign({}, {}, options)
        const tmpApi = new AJAXApi(constAlteredOptions)
        return tmpApi.fetchAndPostForm(url, dataToMerge, constAlteredOptions.skipRequestHooks)
    }

    async fetchURL(url, skipRequestHooks=false) {
        if (!skipRequestHooks) {
            this.beforeRequest()
        }
        let toReturn
        try {
            const response = await fetch(url, AJAXApi.genericRequestConfigGET);
            if (!response.ok) {
                throw new Error('Network response was not ok')
            }
            const data = await response.text();
            this.provideFeedback({
                variant: 'success',
                title: 'URL fetched',
            });
            toReturn = data;
        } catch (error) {
            this.provideFeedback({
                variant: 'danger',
                title: 'There has been a problem with the operation',
                body: error
            }, true);
            toReturn = Promise.reject(error);
        } finally {
            if (!skipRequestHooks) {
                this.afterRequest()
            }
        }
        return toReturn
    }

    async fetchForm(url, skipRequestHooks=false) {
        if (!skipRequestHooks) {
            this.beforeRequest()
        }
        let toReturn
        try {
            const response = await fetch(url, AJAXApi.genericRequestConfigGET);
            if (!response.ok) {
                throw new Error('Network response was not ok')
            }
            const formHtml = await response.text();
            let tmpNode = document.createElement("div");
            tmpNode.innerHTML = formHtml;
            let form = tmpNode.getElementsByTagName('form');
            if (form.length == 0) {
                throw new Error('The server did not return a form element')
            }
            toReturn = form[0];
        } catch (error) {
            this.provideFeedback({
                variant: 'danger',
                title: 'There has been a problem with the operation',
                body: error
            }, true);
            toReturn = Promise.reject(error);
        } finally {
            if (!skipRequestHooks) {
                this.afterRequest()
            }
        }
        return toReturn
    }
    
    async fetchAndPostForm(url, dataToMerge={}, skipRequestHooks=false) {
        if (!skipRequestHooks) {
            this.beforeRequest()
        }
        let toReturn
        try {
            const form = await this.fetchForm(url, true);
            try {
                let formData = new FormData(form)
                formData = AJAXApi.mergeFormData(formData, dataToMerge)
                let options = {
                    ...AJAXApi.genericRequestConfigPOST,
                    body: formData,
                };
                const response = await fetch(form.action, options);
                if (!response.ok) {
                    throw new Error('Network response was not ok')
                }
                const data = await response.json();
                if (data.success) {
                    this.provideFeedback({
                        variant: 'success',
                        body: data.message
                    });
                    toReturn = data;
                } else {
                    this.provideFeedback({
                        variant: 'danger',
                        title: 'There has been a problem with the operation',
                        body: data.errors
                    }, true);
                    toReturn = Promise.reject(error);
                }
            } catch (error) {
                this.provideFeedback({
                    variant: 'danger',
                    title: 'There has been a problem with the operation',
                    body: error
                }, true);
                toReturn = Promise.reject(error);
            }
        } catch (error) {
            toReturn = Promise.reject(error);
        } finally {
            if (!skipRequestHooks) {
                this.afterRequest()
            }
        }
        return toReturn
    }

    beforeRequest() {
        if (this.options.statusNode !== false) {
            this.toggleLoading(true)
        }
    }
    
    afterRequest() {
        if (this.options.statusNode !== false) {
            this.toggleLoading(false)
        }
    }

    toggleLoading(loading) {
        if (this.loadingOverlay === false) {
            this.loadingOverlay = new OverlayFactory({node: this.options.statusNode});
        }
        if (loading) {
            this.loadingOverlay.show()
        } else {
            this.loadingOverlay.hide()
            
        }
    }
}

