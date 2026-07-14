package expose

import (
	daemonProto "github.com/Artee VPNio/Artee VPN/client/proto"
	mgm "github.com/Artee VPNio/Artee VPN/shared/management/client"
)

// NewRequest converts a daemon ExposeServiceRequest to a management ExposeServiceRequest.
func NewRequest(req *daemonProto.ExposeServiceRequest) *Request {
	return &Request{
		Port:       uint16(req.Port),
		Protocol:   ProtocolType(req.Protocol),
		Pin:        req.Pin,
		Password:   req.Password,
		UserGroups: req.UserGroups,
		Domain:     req.Domain,
		NamePrefix: req.NamePrefix,
		ListenPort: uint16(req.ListenPort),
	}
}

func toClientExposeRequest(req Request) mgm.ExposeRequest {
	return mgm.ExposeRequest{
		NamePrefix: req.NamePrefix,
		Domain:     req.Domain,
		Port:       req.Port,
		Protocol:   int(req.Protocol),
		Pin:        req.Pin,
		Password:   req.Password,
		UserGroups: req.UserGroups,
		ListenPort: req.ListenPort,
	}
}

func fromClientExposeResponse(response *mgm.ExposeResponse) *Response {
	return &Response{
		ServiceName:      response.ServiceName,
		Domain:           response.Domain,
		ServiceURL:       response.ServiceURL,
		PortAutoAssigned: response.PortAutoAssigned,
	}
}

