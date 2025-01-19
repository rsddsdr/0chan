ALTER TABLE user_identity ADD CONSTRAINT user_identity_token_key UNIQUE(address);

ALTER TABLE invite ADD CONSTRAINT invite_key UNIQUE(invite);

CREATE INDEX rate_post_id_idx ON public.rate (post_id);

CREATE INDEX board_popularity ON board (popularity DESC NULLS LAST);