const API_BASE = '/api/api.php';

const api = {
    async request(url, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json'
            }
        };
        const config = { ...defaultOptions, ...options };
        if (config.body && typeof config.body !== 'string') {
            config.body = JSON.stringify(config.body);
        }
        
        try {
            const response = await fetch(API_BASE + url, config);
            const data = await response.json();
            if (data.code === 200 || data.code === 201) {
                return data.data;
            } else {
                ElementPlus.ElMessage.error(data.message || '请求失败');
                throw new Error(data.message || '请求失败');
            }
        } catch (error) {
            if (!error.message.includes('请求失败')) {
                ElementPlus.ElMessage.error('网络错误');
            }
            throw error;
        }
    },
    
    get(url) {
        return this.request(url, { method: 'GET' });
    },
    
    post(url, data) {
        return this.request(url, { method: 'POST', body: data });
    },
    
    put(url, data) {
        return this.request(url, { method: 'PUT', body: data });
    },
    
    delete(url) {
        return this.request(url, { method: 'DELETE' });
    },
    
    roles: {
        list(params = {}) {
            const query = new URLSearchParams(params).toString();
            return api.get('/roles' + (query ? `?${query}` : ''));
        },
        detail(id) {
            return api.get('/roles/' + id);
        },
        create(data) {
            return api.post('/roles', data);
        },
        update(id, data) {
            return api.put('/roles/' + id, data);
        },
        delete(id) {
            return api.delete('/roles/' + id);
        },
        menus(id) {
            return api.get(`/roles/${id}/menus`);
        },
        operations(id) {
            return api.get(`/roles/${id}/operations`);
        },
        assignMenus(id, menuIds) {
            return api.post(`/roles/${id}/assignMenus`, { menu_ids: menuIds });
        },
        assignOperations(id, operationIds) {
            return api.post(`/roles/${id}/assignOperations`, { operation_ids: operationIds });
        }
    },
    
    menus: {
        list(params = {}) {
            const query = new URLSearchParams(params).toString();
            return api.get('/menus' + (query ? `?${query}` : ''));
        },
        tree(params = {}) {
            const query = new URLSearchParams(params).toString();
            return api.get('/menus/tree' + (query ? `?${query}` : ''));
        },
        detail(id) {
            return api.get('/menus/' + id);
        },
        create(data) {
            return api.post('/menus', data);
        },
        update(id, data) {
            return api.put('/menus/' + id, data);
        },
        delete(id) {
            return api.delete('/menus/' + id);
        }
    },
    
    operations: {
        list(params = {}) {
            const query = new URLSearchParams(params).toString();
            return api.get('/operations' + (query ? `?${query}` : ''));
        },
        detail(id) {
            return api.get('/operations/' + id);
        },
        create(data) {
            return api.post('/operations', data);
        },
        update(id, data) {
            return api.put('/operations/' + id, data);
        },
        delete(id) {
            return api.delete('/operations/' + id);
        },
        byMenu(menuId) {
            return api.get(`/operations/${menuId}/byMenu`);
        }
    }
};
